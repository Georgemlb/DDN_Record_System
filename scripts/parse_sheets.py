import sys
import os
import json
import zipfile
import re
import xml.etree.ElementTree as ET

def col_letter_to_index(col_str):
    """Convert column letter (e.g., 'A', 'B', 'AA') to 0-based column index."""
    idx = 0
    for char in col_str.upper():
        idx = idx * 26 + (ord(char) - ord('A') + 1)
    return idx - 1

def parse_shared_strings(zf):
    """Parse xl/sharedStrings.xml into a list of strings."""
    shared_strings = []
    if "xl/sharedStrings.xml" not in zf.namelist():
        return shared_strings

    xml_data = zf.read("xl/sharedStrings.xml")
    root = ET.fromstring(xml_data)
    
    # Strip namespace for easier traversal
    ns = '{http://schemas.openxmlformats.org/spreadsheetml/2006/main}'
    for si in root.iter(f"{ns}si"):
        # Could be directly <t> or rich text <r><t>
        texts = []
        for t in si.iter(f"{ns}t"):
            if t.text:
                texts.append(t.text)
        shared_strings.append("".join(texts))
    return shared_strings

def parse_workbook_sheet_map(zf):
    """Map sheet names to their worksheet xml files using workbook.xml and workbook.xml.rels."""
    sheets = []
    if "xl/workbook.xml" not in zf.namelist():
        return sheets

    # Parse rels
    rels = {}
    if "xl/_rels/workbook.xml.rels" in zf.namelist():
        rels_xml = zf.read("xl/_rels/workbook.xml.rels")
        rels_root = ET.fromstring(rels_xml)
        for rel in rels_root:
            rel_id = rel.attrib.get('Id')
            rel_target = rel.attrib.get('Target')
            if rel_id and rel_target:
                # Target is usually like 'worksheets/sheet1.xml' or '/xl/worksheets/sheet1.xml'
                target_path = rel_target.lstrip('/')
                if not target_path.startswith('xl/'):
                    target_path = f"xl/{target_path}"
                rels[rel_id] = target_path

    wb_xml = zf.read("xl/workbook.xml")
    wb_root = ET.fromstring(wb_xml)
    ns = '{http://schemas.openxmlformats.org/spreadsheetml/2006/main}'
    r_ns = '{http://schemas.openxmlformats.org/officeDocument/2006/relationships}'

    for sheet in wb_root.iter(f"{ns}sheet"):
        name = sheet.attrib.get('name')
        r_id = sheet.attrib.get(f"{r_ns}id") or sheet.attrib.get('r:id')
        target_file = rels.get(r_id)
        if name and target_file and target_file in zf.namelist():
            sheets.append({
                "name": name,
                "file": target_file
            })

    return sheets

def parse_sheet_rows(zf, sheet_file, shared_strings):
    """Parse rows and cells of a worksheet file into 2D list."""
    xml_data = zf.read(sheet_file)
    root = ET.fromstring(xml_data)
    ns = '{http://schemas.openxmlformats.org/spreadsheetml/2006/main}'

    rows_data = []
    cell_coord_regex = re.compile(r'([A-Za-z]+)(\d+)')

    for row_elem in root.iter(f"{ns}row"):
        row_cells = {}
        max_col_idx = -1

        for c in row_elem.iter(f"{ns}c"):
            ref = c.attrib.get('r')
            if not ref:
                continue

            match = cell_coord_regex.match(ref)
            if not match:
                continue

            col_letters = match.group(1)
            col_idx = col_letter_to_index(col_letters)
            cell_type = c.attrib.get('t', '')

            val_elem = c.find(f"{ns}v")
            raw_val = val_elem.text if val_elem is not None and val_elem.text is not None else ""

            cell_value = ""
            if cell_type == 's':
                # Shared string
                try:
                    str_idx = int(raw_val)
                    if 0 <= str_idx < len(shared_strings):
                        cell_value = shared_strings[str_idx]
                except ValueError:
                    cell_value = raw_val
            elif cell_type == 'inlineStr':
                # Inline string
                is_elem = c.find(f"{ns}is")
                if is_elem is not None:
                    texts = [t.text for t in is_elem.iter(f"{ns}t") if t.text]
                    cell_value = "".join(texts)
            elif cell_type == 'b':
                cell_value = "TRUE" if raw_val == "1" else "FALSE"
            elif cell_type == 'e':
                cell_value = ""
            else:
                # Number or default
                cell_value = raw_val

            row_cells[col_idx] = cell_value
            if col_idx > max_col_idx:
                max_col_idx = col_idx

        if max_col_idx >= 0:
            row_list = [row_cells.get(i, "").strip() for i in range(max_col_idx + 1)]
            # Only append if row is not completely blank
            if any(bool(cell) for cell in row_list):
                rows_data.append(row_list)

    return rows_data

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Missing file path argument"}))
        sys.exit(1)

    xlsx_path = sys.argv[1]
    if not os.path.exists(xlsx_path):
        print(json.dumps({"error": f"File not found: {xlsx_path}"}))
        sys.exit(1)

    try:
        with zipfile.ZipFile(xlsx_path, 'r') as zf:
            shared_strings = parse_shared_strings(zf)
            sheet_map = parse_workbook_sheet_map(zf)

            result = {"sheets": []}
            for sheet in sheet_map:
                rows = parse_sheet_rows(zf, sheet["file"], shared_strings)
                result["sheets"].append({
                    "name": sheet["name"],
                    "rows": rows
                })

            print(json.dumps(result))
    except Exception as e:
        print(json.dumps({"error": f"Failed to parse Excel file: {str(e)}"}))
        sys.exit(1)

if __name__ == "__main__":
    main()
