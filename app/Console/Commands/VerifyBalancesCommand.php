<?php

namespace App\Console\Commands;

use App\Models\DailySettlement;
use App\Models\FlightPortalDeposit;
use App\Models\GcashLedgerEntry;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use Carbon\Carbon;
use Illuminate\Console\Command;

class VerifyBalancesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ddn:verify-balances
                            {--fix : Safely repair and recalculate detected balance drift}
                            {--date= : Limit audit to a single ISO date (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit transactions, payments, GCash ledger, flight portal deposits, and daily settlements for balance drift or integrity issues.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $dateOption = $this->option('date');
        $filterDate = is_string($dateOption) && trim($dateOption) !== '' ? trim($dateOption) : null;

        if ($filterDate !== null) {
            try {
                $filterDate = Carbon::parse($filterDate)->toDateString();
            } catch (\Exception $e) {
                $this->error("Invalid date format: {$dateOption}. Please use YYYY-MM-DD.");

                return self::FAILURE;
            }
        }

        $this->line('');
        $this->info('======================================================');
        $this->info('  DDN RECORD SYSTEM - FINANCIAL INTEGRITY AUDIT');
        $this->info('======================================================');
        if ($filterDate) {
            $this->comment("Filtering audit for date: {$filterDate}");
        }
        if ($fix) {
            $this->warn('Fix mode is ENABLED: Any detected drift will be repaired.');
        } else {
            $this->line('Running in READ-ONLY mode. Pass --fix to automatically resolve detected drift.');
        }
        $this->line('');

        $totalIssues = 0;
        $totalRepairs = 0;

        // 1. Transaction & Payment Integrity
        $txIssues = $this->auditTransactions($filterDate, $fix);
        $totalIssues += $txIssues['issues'];
        $totalRepairs += $txIssues['repairs'];

        // 2. GCash Ledger Invariance
        $gcashIssues = $this->auditGcashLedger($filterDate, $fix);
        $totalIssues += $gcashIssues['issues'];
        $totalRepairs += $gcashIssues['repairs'];

        // 3. Flight Portal Deposit Invariance
        $portalIssues = $this->auditFlightPortal($filterDate, $fix);
        $totalIssues += $portalIssues['issues'];
        $totalRepairs += $portalIssues['repairs'];

        // 4. Daily Settlement Consistency
        $settlementIssues = $this->auditDailySettlements($filterDate, $fix);
        $totalIssues += $settlementIssues['issues'];
        $totalRepairs += $settlementIssues['repairs'];

        // Summary
        $this->line('');
        $this->info('------------------------------------------------------');
        $this->info('  AUDIT SUMMARY');
        $this->info('------------------------------------------------------');

        $summaryTable = [
            ['Transactions & Payments', $txIssues['audited'], $txIssues['issues'], $txIssues['repairs']],
            ['GCash Ledger Entries', $gcashIssues['audited'], $gcashIssues['issues'], $gcashIssues['repairs']],
            ['Flight Portal Deposits', $portalIssues['audited'], $portalIssues['issues'], $portalIssues['repairs']],
            ['Daily Settlements', $settlementIssues['audited'], $settlementIssues['issues'], $settlementIssues['repairs']],
        ];

        $this->table(['Domain', 'Audited', 'Issues Found', 'Repaired'], $summaryTable);
        $this->line('');

        if ($totalIssues === 0) {
            $this->info('✓ All financial balances and ledger entries are strictly balanced with 0 drift.');

            return self::SUCCESS;
        }

        if ($fix) {
            $this->info("✓ Balance drift resolved: {$totalRepairs} repair action(s) applied.");

            return self::SUCCESS;
        }

        $this->error("✗ {$totalIssues} financial integrity issue(s) detected. Run `php artisan ddn:verify-balances --fix` to repair.");

        return self::FAILURE;
    }

    /**
     * Audit Transactions and their linked payments.
     *
     * @return array{audited: int, issues: int, repairs: int}
     */
    protected function auditTransactions(?string $filterDate, bool $fix): array
    {
        $this->line('<fg=cyan>▶ Auditing Transactions & Payments...</fg=cyan>');

        $query = Transaction::query()->with('payments');
        if ($filterDate) {
            $query->whereDate('transaction_date', $filterDate);
        }

        $transactions = $query->get();
        $issues = 0;
        $repairs = 0;
        $issueRows = [];

        foreach ($transactions as $tx) {
            $amount = (float) $tx->amount;
            $amountPaid = (float) ($tx->amount_paid ?? 0);
            $balance = (float) ($tx->balance ?? 0);
            $paymentsSum = (float) $tx->payments->sum(fn (TransactionPayment $p) => (float) $p->amount);

            $hasDrift = false;
            $reasons = [];

            // Check 1: amount_paid + balance == amount
            if (abs(($amountPaid + $balance) - $amount) > 0.009) {
                $hasDrift = true;
                $reasons[] = sprintf('amount (%.2f) != amount_paid (%.2f) + balance (%.2f)', $amount, $amountPaid, $balance);
            }

            // Check 2: Sum of payments == amount_paid
            if (abs($paymentsSum - $amountPaid) > 0.009) {
                $hasDrift = true;
                $reasons[] = sprintf('payments sum (%.2f) != amount_paid (%.2f)', $paymentsSum, $amountPaid);
            }

            // Check 3: Payment status consistency
            $expectedStatus = Transaction::PAYMENT_STATUS_PARTIAL;
            if ($amountPaid <= 0.009) {
                $expectedStatus = Transaction::PAYMENT_STATUS_UNPAID;
            } elseif ($balance <= 0.009) {
                $expectedStatus = Transaction::PAYMENT_STATUS_PAID;
            }

            if ($tx->payment_status !== $expectedStatus) {
                $hasDrift = true;
                $reasons[] = "status '{$tx->payment_status}' != expected '{$expectedStatus}'";
            }

            // Check 4: Split payment parts add up
            foreach ($tx->payments as $payment) {
                if ($payment->payment_method === Transaction::PAYMENT_METHOD_SPLIT) {
                    $cash = (float) ($payment->cash_amount ?? 0);
                    $gcash = (float) ($payment->gcash_amount ?? 0);
                    $pAmount = (float) $payment->amount;

                    if (abs(($cash + $gcash) - $pAmount) > 0.009) {
                        $hasDrift = true;
                        $reasons[] = sprintf('split payment #%d cash (%.2f) + gcash (%.2f) != amount (%.2f)', $payment->id, $cash, $gcash, $pAmount);
                    }
                }
            }

            if ($hasDrift) {
                $issues++;
                $status = '<fg=red>DRIFT</fg=red>';

                if ($fix) {
                    $tx->recalculateBalance();
                    $repairs++;
                    $status = '<fg=green>REPAIRED</fg=green>';
                }

                $issueRows[] = [
                    $tx->reference_number ?: "ID #{$tx->id}",
                    $tx->transaction_date->format('Y-m-d'),
                    implode('; ', $reasons),
                    $status,
                ];
            }
        }

        if (! empty($issueRows)) {
            $this->table(['Reference', 'Date', 'Discrepancy Details', 'Status'], $issueRows);
        } else {
            $this->line('  ✓ All transaction totals and payments are consistent.');
        }

        return ['audited' => $transactions->count(), 'issues' => $issues, 'repairs' => $repairs];
    }

    /**
     * Audit GCash ledger entries for chronological running balance consistency.
     *
     * @return array{audited: int, issues: int, repairs: int}
     */
    protected function auditGcashLedger(?string $filterDate, bool $fix): array
    {
        $this->line('<fg=cyan>▶ Auditing GCash Ledger Invariance...</fg=cyan>');

        $allEntries = GcashLedgerEntry::orderBy('entry_date', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $running = 0.00;
        $issues = 0;
        $repairs = 0;
        $issueRows = [];
        $driftDetectedInChain = false;

        foreach ($allEntries as $entry) {
            $amount = (float) $entry->amount;
            $recordedBalance = (float) $entry->running_balance;

            if ($entry->is_balance_anchor) {
                $running = $recordedBalance;
            } else {
                if (in_array($entry->type, GcashLedgerEntry::INFLOW_TYPES, true)) {
                    $running += $amount;
                } elseif (in_array($entry->type, GcashLedgerEntry::OUTFLOW_TYPES, true)) {
                    $running -= $amount;
                } elseif ($entry->type === GcashLedgerEntry::TYPE_ADJUSTMENT) {
                    $running += $amount;
                }

                $running = round($running, 2);

                $matchesFilter = $filterDate === null || $entry->entry_date->format('Y-m-d') === $filterDate;

                if (abs($recordedBalance - $running) > 0.009) {
                    $driftDetectedInChain = true;

                    if ($matchesFilter) {
                        $issues++;
                        $status = '<fg=red>DRIFT</fg=red>';

                        if ($fix) {
                            $status = '<fg=green>REPAIRED</fg=green>';
                        }

                        $issueRows[] = [
                            $entry->id,
                            $entry->entry_date->format('Y-m-d'),
                            $entry->type,
                            sprintf('Recorded: %.2f | Expected: %.2f (Diff: %+.2f)', $recordedBalance, $running, $recordedBalance - $running),
                            $status,
                        ];
                    }
                }
            }
        }

        if ($driftDetectedInChain && $fix) {
            GcashLedgerEntry::recalculateRunningBalances();
            $repairs = $issues > 0 ? $issues : 1;
        }

        if (! empty($issueRows)) {
            $this->table(['ID', 'Date', 'Type', 'Discrepancy Details', 'Status'], $issueRows);
        } else {
            $this->line('  ✓ GCash running balance calculations are strictly continuous.');
        }

        $auditedCount = $filterDate
            ? $allEntries->filter(fn (GcashLedgerEntry $e) => $e->entry_date->format('Y-m-d') === $filterDate)->count()
            : $allEntries->count();

        return ['audited' => $auditedCount, 'issues' => $issues, 'repairs' => $repairs];
    }

    /**
     * Audit Flight Portal deposits for chronological running balance consistency.
     *
     * @return array{audited: int, issues: int, repairs: int}
     */
    protected function auditFlightPortal(?string $filterDate, bool $fix): array
    {
        $this->line('<fg=cyan>▶ Auditing Flight Portal Deposit Invariance...</fg=cyan>');

        $allDeposits = FlightPortalDeposit::orderBy('entry_date', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $running = 0.00;
        $issues = 0;
        $repairs = 0;
        $issueRows = [];
        $driftDetectedInChain = false;

        foreach ($allDeposits as $deposit) {
            $amount = (float) $deposit->amount;
            $recordedBalance = (float) $deposit->running_balance;

            if ($deposit->is_balance_anchor) {
                $running = $recordedBalance;
            } else {
                if ($deposit->type === FlightPortalDeposit::TYPE_TOP_UP) {
                    $running += $amount;
                } elseif ($deposit->type === FlightPortalDeposit::TYPE_TICKET_DEDUCTION) {
                    $running -= $amount;
                } elseif ($deposit->type === FlightPortalDeposit::TYPE_ADJUSTMENT) {
                    $running += $amount;
                }

                $running = round($running, 2);

                $matchesFilter = $filterDate === null || $deposit->entry_date->format('Y-m-d') === $filterDate;

                if (abs($recordedBalance - $running) > 0.009) {
                    $driftDetectedInChain = true;

                    if ($matchesFilter) {
                        $issues++;
                        $status = '<fg=red>DRIFT</fg=red>';

                        if ($fix) {
                            $status = '<fg=green>REPAIRED</fg=green>';
                        }

                        $issueRows[] = [
                            $deposit->id,
                            $deposit->entry_date->format('Y-m-d'),
                            $deposit->type,
                            sprintf('Recorded: %.2f | Expected: %.2f (Diff: %+.2f)', $recordedBalance, $running, $recordedBalance - $running),
                            $status,
                        ];
                    }
                }
            }
        }

        if ($driftDetectedInChain && $fix) {
            FlightPortalDeposit::recalculateRunningBalances();
            $repairs = $issues > 0 ? $issues : 1;
        }

        if (! empty($issueRows)) {
            $this->table(['ID', 'Date', 'Type', 'Discrepancy Details', 'Status'], $issueRows);
        } else {
            $this->line('  ✓ Flight portal deposit running balances are strictly continuous.');
        }

        $auditedCount = $filterDate
            ? $allDeposits->filter(fn (FlightPortalDeposit $d) => $d->entry_date->format('Y-m-d') === $filterDate)->count()
            : $allDeposits->count();

        return ['audited' => $auditedCount, 'issues' => $issues, 'repairs' => $repairs];
    }

    /**
     * Audit Daily Settlements against calculated metrics.
     *
     * @return array{audited: int, issues: int, repairs: int}
     */
    protected function auditDailySettlements(?string $filterDate, bool $fix): array
    {
        $this->line('<fg=cyan>▶ Auditing Daily Settlements Consistency...</fg=cyan>');

        $query = DailySettlement::query();
        if ($filterDate) {
            $query->whereDate('settlement_date', $filterDate);
        }

        $settlements = $query->orderBy('settlement_date', 'asc')->get();
        $issues = 0;
        $repairs = 0;
        $issueRows = [];

        foreach ($settlements as $settlement) {
            $dateString = $settlement->settlement_date->format('Y-m-d');
            $metrics = DailySettlement::calculateMetricsForDate($dateString);

            $hasDrift = false;
            $reasons = [];

            // Check metrics match
            if (abs((float) $settlement->cash_in - $metrics['cash_in']) > 0.009) {
                $hasDrift = true;
                $reasons[] = sprintf('cash_in (%.2f) != calculated (%.2f)', (float) $settlement->cash_in, $metrics['cash_in']);
            }

            if (abs((float) $settlement->cash_out - $metrics['cash_out']) > 0.009) {
                $hasDrift = true;
                $reasons[] = sprintf('cash_out (%.2f) != calculated (%.2f)', (float) $settlement->cash_out, $metrics['cash_out']);
            }

            if (abs((float) $settlement->gcash_in - $metrics['gcash_in']) > 0.009) {
                $hasDrift = true;
                $reasons[] = sprintf('gcash_in (%.2f) != calculated (%.2f)', (float) $settlement->gcash_in, $metrics['gcash_in']);
            }

            if (abs((float) $settlement->gcash_out - $metrics['gcash_out']) > 0.009) {
                $hasDrift = true;
                $reasons[] = sprintf('gcash_out (%.2f) != calculated (%.2f)', (float) $settlement->gcash_out, $metrics['gcash_out']);
            }

            // Check expected cash formula: opening_cash + cash_in - cash_out
            $expectedCashFormula = round((float) $settlement->opening_cash + (float) $settlement->cash_in - (float) $settlement->cash_out, 2);
            if (abs((float) $settlement->expected_cash - $expectedCashFormula) > 0.009) {
                $hasDrift = true;
                $reasons[] = sprintf('expected_cash (%.2f) != formula (%.2f)', (float) $settlement->expected_cash, $expectedCashFormula);
            }

            // Check closed settlement discrepancy: actual_cash - expected_cash
            if ($settlement->status === DailySettlement::STATUS_CLOSED && $settlement->actual_cash !== null) {
                $expectedDiscrepancy = round((float) $settlement->actual_cash - (float) $settlement->expected_cash, 2);
                if (abs((float) ($settlement->discrepancy ?? 0) - $expectedDiscrepancy) > 0.009) {
                    $hasDrift = true;
                    $reasons[] = sprintf('discrepancy (%.2f) != actual - expected (%.2f)', (float) $settlement->discrepancy, $expectedDiscrepancy);
                }
            }

            if ($hasDrift) {
                $issues++;
                $status = '<fg=red>DRIFT</fg=red>';

                if ($fix) {
                    $settlement->syncMetricsWithRecords();
                    $settlement->save();
                    $repairs++;
                    $status = '<fg=green>REPAIRED</fg=green>';
                }

                $issueRows[] = [
                    $dateString,
                    $settlement->status,
                    implode('; ', $reasons),
                    $status,
                ];
            }
        }

        if (! empty($issueRows)) {
            $this->table(['Settlement Date', 'Status', 'Discrepancy Details', 'Action'], $issueRows);
        } else {
            $this->line('  ✓ All daily settlement metrics and drawer equations are strictly accurate.');
        }

        return ['audited' => $settlements->count(), 'issues' => $issues, 'repairs' => $repairs];
    }
}
