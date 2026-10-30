<?php

use App\Models\DailySettlement;
use App\Models\FlightPortalDeposit;
use App\Models\GcashLedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\OnlineAssistanceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        OnlineAssistanceTypeSeeder::class,
    ]);
});

test('verify-balances returns success on a clean database', function () {
    $this->artisan('ddn:verify-balances')
        ->expectsOutputToContain('All financial balances and ledger entries are strictly balanced with 0 drift.')
        ->assertSuccessful();
});

test('verify-balances returns success when transactions, gcash ledger, portal deposits and settlements are in sync', function () {
    $user = User::factory()->create();

    // 1. Transaction with payment
    $tx = Transaction::create([
        'user_id' => $user->id,
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-06-01',
        'customer_name' => 'John Doe',
        'amount' => 500.00,
        'amount_paid' => 500.00,
        'balance' => 0.00,
        'payment_status' => Transaction::PAYMENT_STATUS_PAID,
    ]);

    // 2. GCash Ledger with anchor and entry
    GcashLedgerEntry::create([
        'user_id' => $user->id,
        'type' => GcashLedgerEntry::TYPE_ADJUSTMENT,
        'amount' => 10000.00,
        'running_balance' => 10000.00,
        'is_balance_anchor' => true,
        'entry_date' => '2026-06-01',
        'sort_order' => 1,
    ]);

    GcashLedgerEntry::create([
        'user_id' => $user->id,
        'type' => GcashLedgerEntry::TYPE_CUSTOMER_PAYMENT,
        'amount' => 1500.00,
        'running_balance' => 11500.00,
        'is_balance_anchor' => false,
        'entry_date' => '2026-06-01',
        'sort_order' => 2,
    ]);

    // 3. Flight Portal Deposit with top-up
    FlightPortalDeposit::create([
        'user_id' => $user->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 20000.00,
        'running_balance' => 20000.00,
        'is_balance_anchor' => false,
        'entry_date' => '2026-06-01',
        'sort_order' => 1,
    ]);

    // 4. Daily Settlement matching metrics
    $metrics = DailySettlement::calculateMetricsForDate('2026-06-01');
    DailySettlement::create([
        'settlement_date' => '2026-06-01',
        'user_id' => $user->id,
        'status' => DailySettlement::STATUS_OPEN,
        'opening_cash' => 5000.00,
        'cash_in' => $metrics['cash_in'],
        'cash_out' => $metrics['cash_out'],
        'expected_cash' => 5000.00 + $metrics['cash_in'] - $metrics['cash_out'],
        'opening_gcash' => 10000.00,
        'gcash_in' => $metrics['gcash_in'],
        'gcash_out' => $metrics['gcash_out'],
        'expected_gcash' => 10000.00 + $metrics['gcash_in'] - $metrics['gcash_out'],
        'total_gcash' => $metrics['total_gcash'],
        'total_bank_transfer' => $metrics['total_bank_transfer'],
        'total_sales' => $metrics['total_sales'],
        'total_ddn_earnings' => $metrics['total_ddn_earnings'],
    ]);

    $this->artisan('ddn:verify-balances')
        ->expectsOutputToContain('All financial balances and ledger entries are strictly balanced with 0 drift.')
        ->assertSuccessful();
});

test('verify-balances detects transaction balance drift and repairs it with --fix', function () {
    $user = User::factory()->create();

    $tx = Transaction::create([
        'user_id' => $user->id,
        'type' => Transaction::TYPE_AIRLINE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-06-02',
        'customer_name' => 'Jane Smith',
        'amount' => 5000.00,
        'amount_paid' => 2000.00,
        'balance' => 3000.00,
        'payment_status' => Transaction::PAYMENT_STATUS_PARTIAL,
    ]);

    // Tamper with transaction balance quietly
    $tx->updateQuietly([
        'balance' => 100.00, // Inconsistent with 5000 - 2000 = 3000
    ]);

    // Read-only run should detect drift and fail
    $this->artisan('ddn:verify-balances')
        ->expectsOutputToContain('DRIFT')
        ->assertFailed();

    // Fix run should repair and succeed
    $this->artisan('ddn:verify-balances --fix')
        ->expectsOutputToContain('REPAIRED')
        ->assertSuccessful();

    expect((float) $tx->fresh()->balance)->toBe(3000.00);
});

test('verify-balances detects gcash ledger running balance drift and repairs it with --fix', function () {
    $user = User::factory()->create();

    GcashLedgerEntry::create([
        'user_id' => $user->id,
        'type' => GcashLedgerEntry::TYPE_ADJUSTMENT,
        'amount' => 5000.00,
        'running_balance' => 5000.00,
        'is_balance_anchor' => true,
        'entry_date' => '2026-06-03',
        'sort_order' => 1,
    ]);

    $entry = GcashLedgerEntry::create([
        'user_id' => $user->id,
        'type' => GcashLedgerEntry::TYPE_CUSTOMER_PAYMENT,
        'amount' => 1000.00,
        'running_balance' => 6000.00,
        'is_balance_anchor' => false,
        'entry_date' => '2026-06-03',
        'sort_order' => 2,
    ]);

    // Corrupt running balance quietly
    $entry->updateQuietly([
        'running_balance' => 4500.00,
    ]);

    $this->artisan('ddn:verify-balances')
        ->expectsOutputToContain('DRIFT')
        ->assertFailed();

    $this->artisan('ddn:verify-balances --fix')
        ->expectsOutputToContain('REPAIRED')
        ->assertSuccessful();

    expect((float) $entry->fresh()->running_balance)->toBe(6000.00);
});

test('verify-balances detects flight portal running balance drift and repairs it with --fix', function () {
    $user = User::factory()->create();

    FlightPortalDeposit::create([
        'user_id' => $user->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 10000.00,
        'running_balance' => 10000.00,
        'is_balance_anchor' => false,
        'entry_date' => '2026-06-04',
        'sort_order' => 1,
    ]);

    $deduction = FlightPortalDeposit::create([
        'user_id' => $user->id,
        'type' => FlightPortalDeposit::TYPE_TICKET_DEDUCTION,
        'amount' => 4000.00,
        'running_balance' => 6000.00,
        'is_balance_anchor' => false,
        'entry_date' => '2026-06-04',
        'sort_order' => 2,
    ]);

    // Corrupt deduction running balance quietly
    $deduction->updateQuietly([
        'running_balance' => 9000.00,
    ]);

    $this->artisan('ddn:verify-balances')
        ->expectsOutputToContain('DRIFT')
        ->assertFailed();

    $this->artisan('ddn:verify-balances --fix')
        ->expectsOutputToContain('REPAIRED')
        ->assertSuccessful();

    expect((float) $deduction->fresh()->running_balance)->toBe(6000.00);
});

test('verify-balances detects daily settlement metrics drift and repairs it with --fix', function () {
    $user = User::factory()->create();

    $settlement = DailySettlement::create([
        'settlement_date' => '2026-06-05',
        'user_id' => $user->id,
        'status' => DailySettlement::STATUS_OPEN,
        'opening_cash' => 5000.00,
        'cash_in' => 500.00, // Should be 0 since no transactions exist
        'cash_out' => 0.00,
        'expected_cash' => 5500.00,
        'opening_gcash' => 0.00,
        'gcash_in' => 0.00,
        'gcash_out' => 0.00,
        'expected_gcash' => 0.00,
        'total_gcash' => 0.00,
        'total_bank_transfer' => 0.00,
        'total_sales' => 0.00,
        'total_ddn_earnings' => 0.00,
    ]);

    $this->artisan('ddn:verify-balances')
        ->expectsOutputToContain('DRIFT')
        ->assertFailed();

    $this->artisan('ddn:verify-balances --fix')
        ->expectsOutputToContain('REPAIRED')
        ->assertSuccessful();

    $fresh = $settlement->fresh();
    expect((float) $fresh->cash_in)->toBe(0.00);
    expect((float) $fresh->expected_cash)->toBe(5000.00);
});

test('verify-balances supports filtering by --date', function () {
    $user = User::factory()->create();

    // Day 1 has corrupted settlement
    DailySettlement::create([
        'settlement_date' => '2026-06-06',
        'user_id' => $user->id,
        'status' => DailySettlement::STATUS_OPEN,
        'opening_cash' => 5000.00,
        'cash_in' => 888.00, // Drift
        'cash_out' => 0.00,
        'expected_cash' => 5888.00,
        'opening_gcash' => 0.00,
        'gcash_in' => 0.00,
        'gcash_out' => 0.00,
        'expected_gcash' => 0.00,
        'total_gcash' => 0.00,
        'total_bank_transfer' => 0.00,
        'total_sales' => 0.00,
        'total_ddn_earnings' => 0.00,
    ]);

    // Day 2 has clean settlement
    DailySettlement::create([
        'settlement_date' => '2026-06-07',
        'user_id' => $user->id,
        'status' => DailySettlement::STATUS_OPEN,
        'opening_cash' => 5000.00,
        'cash_in' => 0.00,
        'cash_out' => 0.00,
        'expected_cash' => 5000.00,
        'opening_gcash' => 0.00,
        'gcash_in' => 0.00,
        'gcash_out' => 0.00,
        'expected_gcash' => 0.00,
        'total_gcash' => 0.00,
        'total_bank_transfer' => 0.00,
        'total_sales' => 0.00,
        'total_ddn_earnings' => 0.00,
    ]);

    // Auditing Day 2 alone should pass
    $this->artisan('ddn:verify-balances --date=2026-06-07')
        ->expectsOutputToContain('All financial balances and ledger entries are strictly balanced with 0 drift.')
        ->assertSuccessful();

    // Auditing Day 1 alone should fail
    $this->artisan('ddn:verify-balances --date=2026-06-06')
        ->expectsOutputToContain('DRIFT')
        ->assertFailed();
});
