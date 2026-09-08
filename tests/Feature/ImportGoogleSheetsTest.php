<?php

use App\Models\Customer;
use App\Models\DailySettlement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('dry run simulation does not write to the database', function () {
    $filePath = storage_path('app/imports/test_sample.xlsx');

    $exitCode = Artisan::call('import:google-sheets', [
        'file' => $filePath,
        '--dry-run' => true,
    ]);

    expect($exitCode)->toBe(0);
    expect(Transaction::count())->toBe(0);
    expect(Expense::count())->toBe(0);
    expect(DailySettlement::count())->toBe(0);
});

test('actual import successfully creates transactions, expenses, customers, and daily settlements', function () {
    $filePath = storage_path('app/imports/test_sample.xlsx');

    $exitCode = Artisan::call('import:google-sheets', [
        'file' => $filePath,
    ]);

    expect($exitCode)->toBe(0);

    // Verify Transactions
    expect(Transaction::count())->toBe(2);

    $ticketTx = Transaction::where('customer_name', 'Juan Dela Cruz')->first();
    expect($ticketTx)->not->toBeNull();
    expect($ticketTx->type)->toBe(Transaction::TYPE_AIRLINE);
    expect((float) $ticketTx->amount)->toBe(15000.0);
    expect((float) $ticketTx->amount_paid)->toBe(15000.0);
    expect((float) $ticketTx->balance)->toBe(0.0);
    expect($ticketTx->payment_status)->toBe(Transaction::PAYMENT_STATUS_PAID);
    expect($ticketTx->payment_method)->toBe(Transaction::PAYMENT_METHOD_CASH);

    $psaTx = Transaction::where('customer_name', 'Maria Santos')->first();
    expect($psaTx)->not->toBeNull();
    expect($psaTx->type)->toBe(Transaction::TYPE_PSA);
    expect((float) $psaTx->amount)->toBe(700.0); // 500 cash in + 200 balance
    expect((float) $psaTx->amount_paid)->toBe(500.0);
    expect((float) $psaTx->balance)->toBe(200.0);
    expect($psaTx->payment_status)->toBe(Transaction::PAYMENT_STATUS_PARTIAL);
    expect($psaTx->payment_method)->toBe(Transaction::PAYMENT_METHOD_GCASH); // Detected from remarks

    // Verify Customers
    expect(Customer::where('name', 'Juan Dela Cruz')->exists())->toBeTrue();
    expect(Customer::where('name', 'Maria Santos')->exists())->toBeTrue();

    // Verify Expenses & Category Mapping
    expect(Expense::count())->toBe(1);
    $expense = Expense::where('payee', 'Meralco')->first();
    expect($expense)->not->toBeNull();
    expect((float) $expense->amount)->toBe(1200.0);

    $utilitiesCategory = ExpenseCategory::where('name', 'Utilities')->first();
    expect($expense->expense_category_id)->toBe($utilitiesCategory->id);

    // Verify Daily Settlement
    $settlement = DailySettlement::whereDate('settlement_date', '2026-09-01')->first();
    expect($settlement)->not->toBeNull();
    expect((float) $settlement->opening_cash)->toBe(5000.0);
    expect((float) $settlement->actual_cash)->toBe(18800.0);
    expect($settlement->status)->toBe(DailySettlement::STATUS_CLOSED);
});

test('re-running the import command skips duplicates safely', function () {
    $filePath = storage_path('app/imports/test_sample.xlsx');

    Artisan::call('import:google-sheets', ['file' => $filePath]);
    expect(Transaction::count())->toBe(2);
    expect(Expense::count())->toBe(1);

    // Run again
    $exitCode = Artisan::call('import:google-sheets', ['file' => $filePath]);
    expect($exitCode)->toBe(0);

    // Counts remain the same
    expect(Transaction::count())->toBe(2);
    expect(Expense::count())->toBe(1);
});
