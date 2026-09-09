import zipfile
import os

def create_sample_xlsx(filepath):
    os.makedirs(os.path.dirname(os.path.abspath(filepath)), exist_ok=True)
    with zipfile.ZipFile(filepath, 'w') as zf:
        zf.writestr('[Content_Types].xml', """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStringTable+xml"/>
</Types>""")

        zf.writestr('_rels/.rels', """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>""")

        zf.writestr('xl/_rels/workbook.xml.rels', """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>""")

        zf.writestr('xl/workbook.xml', """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Sept 1" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>""")

        zf.writestr('xl/sharedStrings.xml', """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="19" uniqueCount="19">
  <si><t>name</t></si>
  <si><t>particular</t></si>
  <si><t>cash in</t></si>
  <si><t>cash out</t></si>
  <si><t>balance</t></si>
  <si><t>income</t></si>
  <si><t>recievable</t></si>
  <si><t>remarks</t></si>
  <si><t>cash forwarded</t></si>
  <si><t>Juan Dela Cruz</t></si>
  <si><t>TICKET</t></si>
  <si><t>PR123 MNL-DVO</t></si>
  <si><t>Maria Santos</t></si>
  <si><t>BC</t></si>
  <si><t>Birth Certificate gcash</t></si>
  <si><t>Meralco</t></si>
  <si><t>EXPENSES</t></si>
  <si><t>Electric bill Aug</t></si>
  <si><t>box</t></si>
</sst>""")

        zf.writestr('xl/worksheets/sheet1.xml', """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">
      <c r="A1" t="s"><v>0</v></c>
      <c r="B1" t="s"><v>1</v></c>
      <c r="C1" t="s"><v>2</v></c>
      <c r="D1" t="s"><v>3</v></c>
      <c r="E1" t="s"><v>4</v></c>
      <c r="F1" t="s"><v>5</v></c>
      <c r="G1" t="s"><v>6</v></c>
      <c r="H1" t="s"><v>7</v></c>
    </row>
    <row r="2">
      <c r="A2" t="s"><v>8</v></c>
      <c r="C2"><v>5000</v></c>
    </row>
    <row r="3">
      <c r="A3" t="s"><v>9</v></c>
      <c r="B3" t="s"><v>10</v></c>
      <c r="C3"><v>15000</v></c>
      <c r="D3"><v>13500</v></c>
      <c r="E3"><v>0</v></c>
      <c r="F3"><v>1500</v></c>
      <c r="G3"><v>0</v></c>
      <c r="H3" t="s"><v>11</v></c>
    </row>
    <row r="4">
      <c r="A4" t="s"><v>12</v></c>
      <c r="B4" t="s"><v>13</v></c>
      <c r="C4"><v>500</v></c>
      <c r="D4"><v>360</v></c>
      <c r="E4"><v>200</v></c>
      <c r="F4"><v>140</v></c>
      <c r="G4"><v>200</v></c>
      <c r="H4" t="s"><v>14</v></c>
    </row>
    <row r="5">
      <c r="A5" t="s"><v>15</v></c>
      <c r="B5" t="s"><v>16</v></c>
      <c r="C5"><v>0</v></c>
      <c r="D5"><v>1200</v></c>
      <c r="E5"><v>0</v></c>
      <c r="F5"><v>0</v></c>
      <c r="G5"><v>0</v></c>
      <c r="H5" t="s"><v>17</v></c>
    </row>
    <row r="6">
      <c r="A6" t="s"><v>18</v></c>
      <c r="C6"><v>18800</v></c>
    </row>
  </sheetData>
</worksheet>""")

if __name__ == "__main__":
    create_sample_xlsx("storage/app/imports/test_sample.xlsx")
    print("Created storage/app/imports/test_sample.xlsx")
