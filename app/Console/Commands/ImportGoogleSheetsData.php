<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\DailySettlement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FlightPortalDeposit;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class ImportGoogleSheetsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:google-sheets 
                            {file? : Path to the exported .xlsx workbook} 
                            {--year=2026 : Default year for tab dates like "1326" or "Sept 1"} 
                            {--dry-run : Simulate the import without saving changes to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import historical records, transactions, expenses, customers, and daily settlements from an exported Google Sheet (.xlsx)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $defaultYear = (int) $this->option('year') ?: (int) date('Y');

        $this->info('=====================================================');
        $this->info('       DDN Record System - Google Sheets Importer     ');
        $this->info('=====================================================');
        if ($isDryRun) {
            $this->warn('*** DRY RUN MODE: No database changes will be committed ***');
        }

        $filePath = $this->resolveFilePath();
        if (! $filePath) {
            return self::FAILURE;
        }

        $this->line("Target file: <comment>{$filePath}</comment>");
        $this->line("Default year: <comment>{$defaultYear}</comment>");

        $sheetsData = $this->parseWorkbookWithPython($filePath);
        if ($sheetsData === null) {
            return self::FAILURE;
        }

        $sheets = $sheetsData['sheets'] ?? [];
        if (empty($sheets)) {
            $this->error('No sheets found in workbook.');

            return self::FAILURE;
        }

        $this->info(sprintf('Discovered %d sheet(s) in workbook.', count($sheets)));

        $defaultUser = User::where('role', User::ROLE_TECH_ADMIN)->first()
            ?? User::where('role', User::ROLE_ADMIN)->first()
            ?? User::first();

        $categories = ExpenseCategory::all();

        $tableRows = [];
        $totalTransactions = 0;
        $totalExpenses = 0;
        $totalViaDeposits = 0;
        $totalCustomersCreated = 0;
        $totalSettlements = 0;
        $totalSkippedDuplicates = 0;

        foreach ($sheets as $sheet) {
            $sheetName = trim($sheet['name'] ?? '');
            $rows = $sheet['rows'] ?? [];

            if (empty($rows)) {
                continue;
            }

            $date = $this->parseTabDate($sheetName, $defaultYear);
            if (! $date) {
                $this->line("<fg=gray>Skipping non-date tab: '{$sheetName}'</>");

                continue;
            }

            $dateStr = $date->format('Y-m-d');

            $stats = $this->processDaySheet(
                $sheetName,
                $date,
                $rows,
                $defaultUser,
                $categories,
                $isDryRun
            );

            $totalTransactions += $stats['transactions'];
            $totalExpenses += $stats['expenses'];
            $totalViaDeposits += $stats['via_deposits'];
            $totalCustomersCreated += $stats['customers_created'];
            $totalSettlements += ($stats['settlement_created'] ? 1 : 0);
            $totalSkippedDuplicates += $stats['duplicates'];

            $tableRows[] = [
                $dateStr,
                $sheetName,
                $stats['transactions'],
                $stats['expenses'],
                $stats['customers_created'],
                number_format($stats['opening_cash'], 2),
                number_format($stats['closing_box'], 2),
                $stats['duplicates'] > 0 ? "{$stats['duplicates']} dup skipped" : 'OK',
            ];
        }

        $this->newLine();
        // Display latest 30 rows in console table to avoid overwhelming terminal
        $displayRows = count($tableRows) > 30 ? array_slice($tableRows, -30) : $tableRows;
        if (count($tableRows) > 30) {
            $this->comment(sprintf('Showing latest 30 of %d processed days:', count($tableRows)));
        }

        $this->table(
            ['Date', 'Tab Name', 'Transactions', 'Expenses', 'New Customers', 'Opening Cash', 'Closing Box', 'Status'],
            $displayRows
        );

        $this->newLine();
        $this->info('Migration Summary:');
        $this->line(" - Total Days Processed: <comment>{$totalSettlements}</comment>");
        $this->line(" - Total Transactions Imported: <comment>{$totalTransactions}</comment>");
        $this->line(" - Total Expenses Imported: <comment>{$totalExpenses}</comment>");
        $this->line(" - Total Via Portal Deposits: <comment>{$totalViaDeposits}</comment>");
        $this->line(" - New Customer Profiles Created: <comment>{$totalCustomersCreated}</comment>");
        if ($totalSkippedDuplicates > 0) {
            $this->line(" - Duplicates Skipped (Already in DB): <comment>{$totalSkippedDuplicates}</comment>");
        }

        if (! $isDryRun && $totalViaDeposits > 0) {
            FlightPortalDeposit::recalculateRunningBalances();
        }

        if ($isDryRun) {
            $this->warn('Simulation complete. To perform actual import, run without the --dry-run flag.');
        } else {
            $this->info('Import completed successfully!');
        }

        return self::SUCCESS;
    }

    /**
     * Resolve the path to the .xlsx file.
     */
    protected function resolveFilePath(): ?string
    {
        $input = $this->argument('file');

        if ($input) {
            if (file_exists($input)) {
                return realpath($input) ?: $input;
            }

            $storagePath = storage_path("app/imports/{$input}");
            if (file_exists($storagePath)) {
                return realpath($storagePath) ?: $storagePath;
            }

            $this->error("Specified file not found: {$input}");

            return null;
        }

        $importsDir = storage_path('app/imports');
        if (! is_dir($importsDir)) {
            mkdir($importsDir, 0755, true);
        }

        $files = glob("{$importsDir}/*.xlsx");
        if (empty($files)) {
            $this->error("No .xlsx files found in [{$importsDir}].");
            $this->line('Please export your Google Sheet as an Excel file and place it in that folder, or pass the file path directly:');
            $this->line('  php artisan import:google-sheets path/to/file.xlsx');

            return null;
        }

        // Exclude test_sample.xlsx if other files exist
        if (count($files) > 1) {
            $nonSample = array_filter($files, fn ($f) => ! str_ends_with($f, 'test_sample.xlsx'));
            if (! empty($nonSample)) {
                $files = array_values($nonSample);
            }
        }

        return realpath($files[0]) ?: $files[0];
    }

    /**
     * Invoke the Python standard library parser to extract sheets and cells.
     */
    protected function parseWorkbookWithPython(string $filePath): ?array
    {
        $scriptPath = base_path('scripts/parse_sheets.py');
        if (! file_exists($scriptPath)) {
            $this->error("Parser script not found: {$scriptPath}");

            return null;
        }

        $this->line('Reading Excel workbook contents...');

        $process = new Process(['python', $scriptPath, $filePath]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Failed to parse Excel workbook:');
            $this->line($process->getErrorOutput());

            return null;
        }

        $output = trim($process->getOutput());
        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            $this->error('Invalid JSON response from Excel parser: '.json_last_error_msg());

            return null;
        }

        if (isset($data['error'])) {
            $this->error("Parser error: {$data['error']}");

            return null;
        }

        return $data;
    }

    /**
     * Parse date from tab name (e.g. "1326", "11026", "12026", "8132026", "Sept 1", "2026-09-01").
     */
    protected function parseTabDate(string $tabName, int $defaultYear): ?Carbon
    {
        $clean = trim($tabName);
        $twoDigitYear = substr((string) $defaultYear, -2);

        // 1. Check compact numeric format with 4-digit year (e.g. "8132026")
        if (strlen($clean) >= 7 && str_ends_with($clean, (string) $defaultYear)) {
            $prefix = substr($clean, 0, -4);
            $month = null;
            $day = null;
            if (strlen($prefix) === 2) {
                $month = (int) $prefix[0];
                $day = (int) $prefix[1];
            } elseif (strlen($prefix) === 3) {
                $month = (int) $prefix[0];
                $day = (int) substr($prefix, 1);
            } elseif (strlen($prefix) === 4) {
                $month = (int) substr($prefix, 0, 2);
                $day = (int) substr($prefix, 2);
            }

            if ($month && $day) {
                try {
                    return Carbon::create($defaultYear, $month, $day)->startOfDay();
                } catch (\Throwable) {
                }
            }
        }

        // 2. Check compact numeric format with 2-digit year (e.g. "1326", "11026", "12026", "92426", "10126", "101526")
        if (str_ends_with($clean, $twoDigitYear) && strlen($clean) >= 4 && strlen($clean) <= 6 && ctype_digit($clean)) {
            $prefix = substr($clean, 0, -2);
            $month = null;
            $day = null;

            if (strlen($prefix) === 2) {
                // E.g. "13" -> 1 / 3
                $month = (int) $prefix[0];
                $day = (int) $prefix[1];
            } elseif (strlen($prefix) === 3) {
                // E.g. "101" -> 10 / 1; "105" -> 10 / 5; "120" -> 1 / 20; "924" -> 9 / 24
                if (str_starts_with($prefix, '10') && (int) $prefix[2] >= 1 && (int) $prefix[2] <= 9) {
                    $month = 10;
                    $day = (int) $prefix[2];
                } elseif (str_starts_with($prefix, '11') && (int) $prefix[2] >= 1 && (int) $prefix[2] <= 9 && (int) substr($prefix, 1) > 31) {
                    $month = 11;
                    $day = (int) $prefix[2];
                } elseif (str_starts_with($prefix, '12') && (int) $prefix[2] >= 1 && (int) $prefix[2] <= 9 && (int) substr($prefix, 1) > 31) {
                    $month = 12;
                    $day = (int) $prefix[2];
                } else {
                    $month = (int) $prefix[0];
                    $day = (int) substr($prefix, 1);
                }
            } elseif (strlen($prefix) === 4) {
                // E.g. "1015" -> 10 / 15
                $month = (int) substr($prefix, 0, 2);
                $day = (int) substr($prefix, 2);
            }

            if ($month && $day) {
                try {
                    return Carbon::create($defaultYear, $month, $day)->startOfDay();
                } catch (\Throwable) {
                }
            }
        }

        // 3. Standard YYYY-MM-DD or YYYY/MM/DD
        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $clean, $m)) {
            return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
        }

        // 4. Normalize "Sept" -> "Sep" and check text dates like "Sep 1", "Sep 15", "October 3"
        $normalized = preg_replace('/sept\b/i', 'Sep', $clean);
        if (preg_match('/(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s*(\d{1,2})(?:st|nd|rd|th)?(?:[,\s]+(\d{4}))?/i', $normalized, $m)) {
            $year = ! empty($m[3]) ? (int) $m[3] : $defaultYear;

            try {
                return Carbon::parse("{$m[1]} {$m[2]} {$year}")->startOfDay();
            } catch (\Throwable) {
            }
        }

        // 5. MM-DD or MM/DD
        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})$/', $clean, $m)) {
            try {
                return Carbon::create($defaultYear, (int) $m[1], (int) $m[2])->startOfDay();
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /**
     * Find the header row and map column names to indices.
     */
    protected function findHeaderRow(array $rows): ?array
    {
        foreach ($rows as $rIdx => $row) {
            $map = [];
            foreach ($row as $cIdx => $cell) {
                $cellLower = strtolower(trim((string) $cell));

                if (str_contains($cellLower, 'name') || str_contains($cellLower, 'customer') || str_contains($cellLower, 'client') || str_contains($cellLower, 'payee')) {
                    $map['name'] = $cIdx;
                } elseif (str_contains($cellLower, 'particular') || str_contains($cellLower, 'service') || str_contains($cellLower, 'type')) {
                    $map['particular'] = $cIdx;
                } elseif (str_contains($cellLower, 'cash in') || $cellLower === 'in' || str_contains($cellLower, 'cash_in')) {
                    $map['cash_in'] = $cIdx;
                } elseif (str_contains($cellLower, 'cash out') || $cellLower === 'out' || str_contains($cellLower, 'cash_out') || str_contains($cellLower, 'cost') || str_contains($cellLower, 'expenses')) {
                    $map['cash_out'] = $cIdx;
                } elseif (str_contains($cellLower, 'balance') || str_contains($cellLower, 'bal')) {
                    $map['balance'] = $cIdx;
                } elseif (str_contains($cellLower, 'income') || str_contains($cellLower, 'profit') || str_contains($cellLower, 'markup')) {
                    $map['income'] = $cIdx;
                } elseif (str_contains($cellLower, 'recievable') || str_contains($cellLower, 'receivable')) {
                    $map['receivable'] = $cIdx;
                } elseif (str_contains($cellLower, 'remark') || str_contains($cellLower, 'note') || str_contains($cellLower, 'detail')) {
                    $map['remarks'] = $cIdx;
                }
            }

            // If particular column was found and cash in/out was found, this is the header
            if (isset($map['particular']) && (isset($map['cash_in']) || isset($map['cash_out']))) {
                if (! isset($map['name'])) {
                    $map['name'] = 0;
                }

                return [
                    'rowIndex' => $rIdx,
                    'map' => $map,
                ];
            }
        }

        return null;
    }

    /**
     * Process all rows for a single day sheet tab.
     *
     * @return array{transactions: int, expenses: int, via_deposits: int, customers_created: int, duplicates: int, opening_cash: float, closing_box: float, settlement_created: bool}
     */
    protected function processDaySheet(
        string $sheetName,
        Carbon $date,
        array $rows,
        ?User $user,
        Collection $categories,
        bool $isDryRun
    ): array {
        $headerInfo = $this->findHeaderRow($rows);
        $startRow = $headerInfo ? $headerInfo['rowIndex'] + 1 : 1;
        $map = $headerInfo['map'] ?? [
            'name' => 0,
            'particular' => 1,
            'cash_in' => 2,
            'cash_out' => 3,
            'balance' => 4,
            'income' => 5,
            'receivable' => 6,
            'remarks' => 7,
        ];

        $openingCash = 0.0;
        $closingBox = 0.0;
        $closingVia = null;
        $closingGcash = null;

        $transactionsCount = 0;
        $expensesCount = 0;
        $viaDepositsCount = 0;
        $customersCreated = 0;
        $duplicatesCount = 0;

        $dayCashIn = 0.0;
        $dayCashOut = 0.0;

        $processRowAction = function () use (
            $sheetName,
            $rows,
            $startRow,
            $map,
            $date,
            $user,
            $categories,
            $isDryRun,
            &$openingCash,
            &$closingBox,
            &$closingVia,
            &$closingGcash,
            &$transactionsCount,
            &$expensesCount,
            &$viaDepositsCount,
            &$customersCreated,
            &$duplicatesCount,
            &$dayCashIn,
            &$dayCashOut
        ) {
            for ($i = $startRow; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (! is_array($row) || empty(array_filter($row, fn ($c) => trim((string) $c) !== ''))) {
                    continue;
                }

                $rowStr = implode(' ', array_map(fn ($c) => trim((string) $c), $row));

                $name = trim((string) ($row[$map['name'] ?? 0] ?? ''));
                $particular = trim((string) ($row[$map['particular'] ?? 1] ?? ''));
                $cashIn = $this->parseMoney($row[$map['cash_in'] ?? 2] ?? null);
                $cashOut = $this->parseMoney($row[$map['cash_out'] ?? 3] ?? null);
                $balance = $this->parseMoney($row[$map['balance'] ?? 4] ?? null);
                $income = $this->parseMoney($row[$map['income'] ?? 5] ?? null);
                $remarks = trim((string) ($row[$map['remarks'] ?? 7] ?? ''));

                $nameLower = strtolower($name);
                $particularLower = strtolower($particular);
                $remarksLower = strtolower($remarks);

                // Check 1: Box balance anywhere in the row string (e.g. "BOX=455", "CASH IN THE BOX = 435", "BOX = 720")
                if (preg_match('/(?:box|cash in the box)\s*[:=]\s*([0-9.,]+)/i', $rowStr, $boxMatch)) {
                    $closingBox = $this->parseMoney($boxMatch[1]);

                    continue;
                }

                if (preg_match('/^box\b/i', $name) || preg_match('/^box\b/i', $particular) || str_contains($nameLower, 'cash in the box')) {
                    $closingBox = $cashIn > 0 ? $cashIn : ($cashOut > 0 ? $cashOut : ($balance > 0 ? $balance : $income));

                    continue;
                }

                // Check 2: Via portal closing balance anywhere in the row string (e.g. "VIA=29,168.85")
                if (preg_match('/(?:via|via portal)\s*[:=]\s*([0-9.,]+)/i', $rowStr, $viaMatch)) {
                    $closingVia = $this->parseMoney($viaMatch[1]);

                    continue;
                }

                if (preg_match('/^via\b/i', $name) || str_contains($nameLower, 'via portal')) {
                    $closingVia = $cashIn > 0 ? $cashIn : ($cashOut > 0 ? $cashOut : $balance);

                    continue;
                }

                // Check 3: GCash ending balance anywhere in the row string (e.g. "GCASH = 5000")
                if (preg_match('/(?:gcash|g-cash)\s*[:=]\s*([0-9.,]+)/i', $rowStr, $gcashMatch)) {
                    $closingGcash = $this->parseMoney($gcashMatch[1]);

                    continue;
                }

                if (preg_match('/^gcash\b/i', $name) && empty($particular) && $cashOut == 0) {
                    $closingGcash = $cashIn > 0 ? $cashIn : ($cashOut > 0 ? $cashOut : $balance);

                    continue;
                }

                // Skip header repetition or empty subtotals
                if ($particularLower === 'particular service' || $particularLower === 'particular' || ($name === '' && $particular === '')) {
                    continue;
                }

                // Check 4: Opening cash float ("cash forwarded")
                if (str_contains($particularLower, 'forwarded') || str_contains($particularLower, 'fowarded') || str_contains($nameLower, 'forwarded')) {
                    $openingCash = $cashIn > 0 ? $cashIn : ($cashOut > 0 ? $cashOut : $balance);

                    continue;
                }

                // Check 5: Via Portal Deposit ("VIA DEPOSIT")
                if (str_contains($particularLower, 'via deposit')) {
                    $depositAmount = $cashOut > 0 ? $cashOut : $cashIn;
                    if ($depositAmount > 0) {
                        $isGcash = str_contains($remarksLower, 'gcash');
                        if (! $isGcash) {
                            $dayCashOut += $depositAmount;
                        }

                        if (! $isDryRun) {
                            $exists = FlightPortalDeposit::whereDate('entry_date', $date->toDateString())
                                ->where('type', FlightPortalDeposit::TYPE_TOP_UP)
                                ->where('amount', $depositAmount)
                                ->exists();

                            if (! $exists) {
                                FlightPortalDeposit::create([
                                    'user_id' => $user?->id,
                                    'type' => FlightPortalDeposit::TYPE_TOP_UP,
                                    'amount' => $depositAmount,
                                    'running_balance' => 0.00,
                                    'payment_source' => $isGcash ? FlightPortalDeposit::PAYMENT_SOURCE_GCASH : FlightPortalDeposit::PAYMENT_SOURCE_CASH,
                                    'entry_date' => $date->toDateString(),
                                    'notes' => $remarks ?: "Via deposit from {$sheetName}",
                                ]);
                            }
                        }

                        $viaDepositsCount++;
                    }

                    continue;
                }

                // Check 6: Expense
                $isExplicitExpense = str_contains($particularLower, 'expense') || str_contains($particularLower, 'gastos');
                $isGeneralExpense = ($name === '' && $cashIn == 0 && $cashOut > 0)
                    || preg_match('/\b(food|salary|fare|shopee|shoppee|permit|water|electric|meralco|internet|pldt|globe|supplies|tape|paper|ink|repair|maintenance|load)\b/i', $particular);

                if ($isExplicitExpense || $isGeneralExpense) {
                    $amount = $cashOut > 0 ? $cashOut : $cashIn;
                    if ($amount <= 0) {
                        continue;
                    }

                    $isGcash = str_contains($remarksLower, 'gcash') || str_contains($particularLower, 'gcash');
                    $payee = $name !== '' ? $name : $particular;
                    $title = $particular !== '' ? $particular : ($name ?: 'General Expense');
                    $categoryId = $this->matchExpenseCategory("{$title} {$payee} {$remarks}", $categories);

                    // Duplicate check
                    $exists = Expense::whereDate('expense_date', $date->toDateString())
                        ->where('amount', $amount)
                        ->where('title', $title)
                        ->exists();

                    if ($exists) {
                        $duplicatesCount++;

                        continue;
                    }

                    if (! $isGcash) {
                        $dayCashOut += $amount;
                    }

                    if (! $isDryRun) {
                        Expense::create([
                            'user_id' => $user?->id,
                            'title' => $title,
                            'expense_category_id' => $categoryId,
                            'amount' => $amount,
                            'expense_date' => $date->toDateString(),
                            'payment_method' => $isGcash ? Expense::PAYMENT_METHOD_GCASH : Expense::PAYMENT_METHOD_CASH,
                            'payee' => $payee,
                            'notes' => $remarks ?: null,
                        ]);
                    }

                    $expensesCount++;

                    continue;
                }

                // Check 7: Transaction
                if ($name !== '' || $particular !== '' || $cashIn > 0 || $cashOut > 0) {
                    $type = $this->resolveTransactionType($particular, $remarks);
                    $isGcash = str_contains($remarksLower, 'gcash') || str_contains($particularLower, 'gcash');

                    $totalAmount = ($cashIn + $balance) > 0 ? ($cashIn + $balance) : $cashIn;
                    if ($totalAmount <= 0) {
                        continue;
                    }

                    $amountPaid = $cashIn;
                    $remBalance = $balance;
                    $paymentStatus = $remBalance <= 0
                        ? Transaction::PAYMENT_STATUS_PAID
                        : ($amountPaid > 0 ? Transaction::PAYMENT_STATUS_PARTIAL : Transaction::PAYMENT_STATUS_UNPAID);

                    // Duplicate check
                    $exists = Transaction::whereDate('transaction_date', $date->toDateString())
                        ->where('customer_name', $name ?: 'Walk-in Customer')
                        ->where('amount', $totalAmount)
                        ->exists();

                    if ($exists) {
                        $duplicatesCount++;

                        continue;
                    }

                    if (! $isGcash) {
                        $dayCashIn += $amountPaid;
                        if ($cashOut > 0) {
                            $dayCashOut += $cashOut;
                        }
                    }

                    $customerId = null;
                    if ($name !== '') {
                        if (! $isDryRun) {
                            $customer = Customer::firstOrCreate(['name' => $name]);
                            if ($customer->wasRecentlyCreated) {
                                $customersCreated++;
                            }
                            $customerId = $customer->id;
                        } else {
                            $existingCustomer = Customer::where('name', $name)->first();
                            if (! $existingCustomer) {
                                $customersCreated++;
                            }
                        }
                    }

                    $pax = null;
                    if (preg_match('/(\d+)\s*pax/i', "{$particular} {$remarks}", $paxMatch)) {
                        $pax = (int) $paxMatch[1];
                    }

                    $provider = null;
                    $upperDetails = strtoupper("{$particular} {$remarks}");
                    if (str_contains($upperDetails, '5J')) {
                        $provider = 'Cebu Pacific';
                    } elseif (str_contains($upperDetails, 'PR')) {
                        $provider = 'Philippine Airlines';
                    } elseif (str_contains($upperDetails, 'Z2')) {
                        $provider = 'AirAsia';
                    }

                    $flightType = null;
                    if (str_contains($upperDetails, 'RT') || str_contains($upperDetails, 'ROUND TRIP')) {
                        $flightType = 'round_trip';
                    } elseif (str_contains($upperDetails, 'OW') || str_contains($upperDetails, 'ONE WAY')) {
                        $flightType = 'one_way';
                    }

                    $psaType = null;
                    if ($type === Transaction::TYPE_PSA) {
                        if (str_contains($upperDetails, 'CENOMAR')) {
                            $psaType = Transaction::PSA_DOC_CENOMAR;
                        } elseif (str_contains($upperDetails, 'MC') || str_contains($upperDetails, 'MARRIAGE')) {
                            $psaType = Transaction::PSA_DOC_MC;
                        } elseif (str_contains($upperDetails, 'DC') || str_contains($upperDetails, 'DEATH')) {
                            $psaType = Transaction::PSA_DOC_DC;
                        } else {
                            $psaType = Transaction::PSA_DOC_BC;
                        }
                    }

                    if (! $isDryRun) {
                        Transaction::create([
                            'user_id' => $user?->id,
                            'customer_id' => $customerId,
                            'type' => $type,
                            'payment_method' => $isGcash ? Transaction::PAYMENT_METHOD_GCASH : Transaction::PAYMENT_METHOD_CASH,
                            'transaction_date' => $date->toDateString(),
                            'customer_name' => $name ?: 'Walk-in Customer',
                            'pax' => $pax,
                            'provider' => $provider,
                            'flight_type' => $flightType,
                            'psa_document_type' => $psaType,
                            'amount' => $totalAmount,
                            'amount_paid' => $amountPaid,
                            'balance' => $remBalance,
                            'payment_status' => $paymentStatus,
                            'cash_amount' => ! $isGcash ? $amountPaid : 0.00,
                            'gcash_amount' => $isGcash ? $amountPaid : 0.00,
                            'third_party_payment' => $cashOut > 0 ? $cashOut : null,
                            'ddn_amount' => $income > 0 ? $income : ($totalAmount - ($cashOut ?: 0)),
                            'notes' => $remarks ?: null,
                            'service_details' => $particular ?: ($remarks ?: null),
                        ]);
                    }

                    $transactionsCount++;
                }
            }
        };

        if ($isDryRun) {
            $processRowAction();
        } else {
            DB::transaction($processRowAction);
        }

        // Daily Settlement for this date
        $expectedCash = $openingCash + $dayCashIn - $dayCashOut;
        if ($closingBox == 0.0 && $expectedCash > 0) {
            $closingBox = $expectedCash;
        }

        $settlementCreated = false;
        if (! $isDryRun) {
            $existingSettlement = DailySettlement::whereDate('settlement_date', $date->toDateString())->first();
            if (! $existingSettlement) {
                $notes = "Imported from Google Sheet tab: {$sheetName}";
                if ($closingVia !== null) {
                    $notes .= sprintf(' | Ending Via: ₱%s', number_format($closingVia, 2));
                }
                if ($closingGcash !== null) {
                    $notes .= sprintf(' | Ending GCash: ₱%s', number_format($closingGcash, 2));
                }

                DailySettlement::create([
                    'settlement_date' => $date->toDateString(),
                    'user_id' => $user?->id,
                    'closed_by_user_id' => $user?->id,
                    'status' => DailySettlement::STATUS_CLOSED,
                    'opening_cash' => $openingCash,
                    'cash_in' => $dayCashIn,
                    'cash_out' => $dayCashOut,
                    'expected_cash' => $expectedCash,
                    'actual_cash' => $closingBox,
                    'discrepancy' => round($closingBox - $expectedCash, 2),
                    'total_sales' => $dayCashIn,
                    'notes' => $notes,
                    'opened_at' => $date->copy()->setTime(8, 0),
                    'closed_at' => $date->copy()->setTime(17, 30),
                ]);
                $settlementCreated = true;
            }
        } else {
            $settlementCreated = true;
        }

        return [
            'transactions' => $transactionsCount,
            'expenses' => $expensesCount,
            'via_deposits' => $viaDepositsCount,
            'customers_created' => $customersCreated,
            'duplicates' => $duplicatesCount,
            'opening_cash' => $openingCash,
            'closing_box' => $closingBox,
            'settlement_created' => $settlementCreated,
        ];
    }

    /**
     * Resolve transaction type based on particular and remarks.
     */
    protected function resolveTransactionType(string $particular, string $remarks): string
    {
        $text = strtolower("{$particular} {$remarks}");

        if (str_contains($text, 'ticket') || str_contains($text, 'tixket') || str_contains($text, 'flight') || str_contains($text, 'airline') || str_contains($text, 'cebu') || str_contains($text, 'pal') || str_contains($text, 'baggage')) {
            return Transaction::TYPE_AIRLINE;
        }

        if (str_contains($text, 'bc') || str_contains($text, 'psa') || str_contains($text, 'birth cert') || str_contains($text, 'cenomar') || str_contains($text, 'death cert') || str_contains($text, 'marriage') || str_contains($text, 'mc') || str_contains($text, 'dc')) {
            return Transaction::TYPE_PSA;
        }

        if (str_contains($text, 'translation') || str_contains($text, 'translayion')) {
            return Transaction::TYPE_TRANSLATION;
        }

        if (str_contains($text, 'print') || str_contains($text, 'photo') || str_contains($text, 'laminat') || str_contains($text, 'xerox') || str_contains($text, 'scan') || str_contains($text, 'resume')) {
            return Transaction::TYPE_PRINTING;
        }

        return Transaction::TYPE_ONLINE_ASSISTANCE;
    }

    /**
     * Match expense category by keywords or fallback to Miscellaneous.
     */
    protected function matchExpenseCategory(string $text, Collection $categories): int
    {
        $text = strtolower($text);

        $keywords = [
            'Utilities' => ['meralco', 'electric', 'kuryente', 'water', 'tubig', 'internet', 'pldt', 'globe', 'wifi', 'utility', 'bill', 'load'],
            'Office Supplies' => ['supplies', 'paper', 'bond paper', 'pen', 'folder', 'envelope', 'tape', 'stapler', 'office', 'shopee', 'shoppee'],
            'Printing Supplies' => ['ink', 'toner', 'cartridge', 'photo paper', 'glossy', 'ribbon'],
            'Rent' => ['rent', 'lease', 'rental', 'space'],
            'Travel & Transportation' => ['fare', 'gas', 'gasoline', 'fuel', 'jeep', 'tricycle', 'trike', 'grab', 'taxi', 'travel', 'transport', 'parking'],
            'Equipment & Hardware' => ['hardware', 'equipment', 'mouse', 'keyboard', 'cable', 'monitor', 'printer', 'repair'],
        ];

        foreach ($keywords as $categoryName => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    $cat = $categories->firstWhere('name', $categoryName);
                    if ($cat) {
                        return (int) $cat->id;
                    }
                }
            }
        }

        $misc = $categories->firstWhere('name', 'Miscellaneous') ?? $categories->first();

        return $misc ? (int) $misc->id : 1;
    }

    /**
     * Clean and parse currency/numerical strings.
     */
    protected function parseMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9.-]/', '', (string) $value);

        return (float) $clean;
    }
}
