<?php

namespace App\Console\Commands;

use App\Models\AirlineMarkup;
use App\Models\Customer;
use App\Models\DailySettlement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FlightPortalDeposit;
use App\Models\GcashLedgerEntry;
use App\Models\OnlineAssistanceType;
use App\Models\PsaRate;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class FreshStartResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:fresh-start {--force : Force execute the reset without confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Wipe operational data (transactions, expenses, settlements, ledger, customers) for a fresh start while preserving system configuration and users.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Are you sure you want to wipe all transactions, expenses, settlements, ledger entries, and customers?')) {
            $this->warn('Fresh start reset cancelled.');

            return self::SUCCESS;
        }

        $this->info('=== STARTING FRESH START RESET ===');

        $isSqlite = DB::getDriverName() === 'sqlite';

        // Step 1: Backup current database (if SQLite file exists)
        if ($isSqlite) {
            $dbPath = database_path('database.sqlite');
            $archivePath = database_path('database_archive_pre_fresh_start.sqlite');

            if (File::exists($dbPath)) {
                File::copy($dbPath, $archivePath);
                $this->info("Archived current database to: <comment>{$archivePath}</comment>");
            }
        }

        // Tables to wipe
        $tablesToWipe = [
            'transactions',
            'transaction_payments',
            'expenses',
            'daily_settlements',
            'flight_portal_deposits',
            'gcash_ledger_entries',
            'customers',
        ];

        $this->info('Wiping operational tables and resetting auto-increment counters...');

        DB::transaction(function () use ($tablesToWipe, $isSqlite) {
            Schema::disableForeignKeyConstraints();

            foreach ($tablesToWipe as $table) {
                DB::table($table)->truncate();
                $this->line(" - Truncated table: <comment>{$table}</comment>");

                // Reset auto-increment counter on SQLite
                if ($isSqlite) {
                    DB::statement("DELETE FROM sqlite_sequence WHERE name = '{$table}';");
                }
            }

            Schema::enableForeignKeyConstraints();
        });

        $this->line('');
        $this->info('Verifying Table Record Counts:');

        $counts = [
            ['Transactions', Transaction::withTrashed()->count(), '0 (Clean)'],
            ['Transaction Payments', TransactionPayment::count(), '0 (Clean)'],
            ['Expenses', Expense::withTrashed()->count(), '0 (Clean)'],
            ['Daily Settlements', DailySettlement::withTrashed()->count(), '0 (Clean)'],
            ['Flight Portal Ledger Entries', FlightPortalDeposit::withTrashed()->count(), '0 (Clean)'],
            ['GCash Ledger Entries', GcashLedgerEntry::withTrashed()->count(), '0 (Clean)'],
            ['Customers', Customer::withTrashed()->count(), '0 (Clean)'],
            ['Users (Accounts)', User::count(), 'Preserved'],
            ['Airline Markup Rules', AirlineMarkup::count(), 'Preserved'],
            ['PSA Rates', PsaRate::count(), 'Preserved'],
            ['Online Assistance Types', OnlineAssistanceType::count(), 'Preserved'],
            ['Expense Categories', ExpenseCategory::count(), 'Preserved'],
        ];

        $this->table(['Table / Model', 'Current Count', 'Status'], $counts);

        $this->line('');
        $this->info('=== FRESH START RESET COMPLETE ===');
        $this->info('The system is ready for clean recording. All new records will begin at ID #1 and TRX-...-0001.');

        return self::SUCCESS;
    }
}
