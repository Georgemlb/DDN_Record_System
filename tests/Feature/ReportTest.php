<?php

use App\Models\DailySettlement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('guests are redirected to the login page when accessing reports', function () {
    $this->get(route('reports.index'))->assertRedirect(route('login'));
    $this->post(route('reports.settlements.start'))->assertRedirect(route('login'));
    $this->get(route('reports.settlements.preview'))->assertRedirect(route('login'));
    $this->post(route('reports.settlements.store-past'))->assertRedirect(route('login'));
    $this->get(route('reports.export'))->assertRedirect(route('login'));
});

test('authenticated users can view reports hub', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->has('currentTab')
            ->has('dailySettlements')
            ->has('financialReports')
            ->where('financialReports.period', 'this_month')
        );
});

test('user can open a daily cash register with opening float', function () {
    $user = User::factory()->create();
    $today = '2026-09-07';
    Carbon::setTestNow(Carbon::parse($today));

    // Simulate existing cash transaction today
    Transaction::query()->create([
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => $today,
        'customer_name' => 'Walk-in Customer',
        'amount' => 500.00,
        'ddn_amount' => 500.00,
    ]);

    // Simulate existing cash expense today
    $cat = ExpenseCategory::query()->create(['name' => 'Supplies']);
    Expense::query()->create([
        'expense_category_id' => $cat->id,
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'expense_date' => $today,
        'title' => 'Paper ream',
        'amount' => 200.00,
    ]);

    $response = $this->actingAs($user)->post(route('reports.settlements.start'), [
        'opening_cash' => 1000.00,
        'settlement_date' => $today,
    ]);

    $response->assertRedirect();

    $settlement = DailySettlement::query()->whereDate('settlement_date', $today)->first();
    expect($settlement)->not->toBeNull();
    expect($settlement->status)->toBe(DailySettlement::STATUS_OPEN);
    expect((float) $settlement->opening_cash)->toBe(1000.00);
    expect((float) $settlement->cash_in)->toBe(500.00);
    expect((float) $settlement->cash_out)->toBe(200.00);
    expect((float) $settlement->expected_cash)->toBe(1300.00); // 1000 + 500 - 200
});

test('user cannot open duplicate settlement for the same date', function () {
    $user = User::factory()->create();
    $today = '2026-09-07';
    Carbon::setTestNow(Carbon::parse($today));

    DailySettlement::query()->create([
        'settlement_date' => $today,
        'user_id' => $user->id,
        'status' => DailySettlement::STATUS_OPEN,
        'opening_cash' => 500.00,
        'cash_in' => 0,
        'cash_out' => 0,
        'expected_cash' => 500.00,
    ]);

    $response = $this->actingAs($user)->post(route('reports.settlements.start'), [
        'opening_cash' => 800.00,
        'settlement_date' => $today,
    ]);

    $response->assertRedirect();
    expect(DailySettlement::query()->whereDate('settlement_date', $today)->count())->toBe(1);
});

test('user can close daily register and discrepancy is computed', function () {
    $user = User::factory()->create();
    $today = '2026-09-07';
    Carbon::setTestNow(Carbon::parse($today));

    Transaction::query()->create([
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => $today,
        'customer_name' => 'Cash Customer',
        'amount' => 500.00,
        'ddn_amount' => 500.00,
    ]);

    $cat = ExpenseCategory::query()->create(['name' => 'General']);
    Expense::query()->create([
        'expense_category_id' => $cat->id,
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'expense_date' => $today,
        'title' => 'Office supplies',
        'amount' => 100.00,
    ]);

    $settlement = DailySettlement::query()->create([
        'settlement_date' => $today,
        'user_id' => $user->id,
        'status' => DailySettlement::STATUS_OPEN,
        'opening_cash' => 1000.00,
        'cash_in' => 500.00,
        'cash_out' => 100.00,
        'expected_cash' => 1400.00,
    ]);

    // Counted cash is 1380 (20 pesos shortage)
    $response = $this->actingAs($user)->put(route('reports.settlements.close', $settlement), [
        'actual_cash' => 1380.00,
        'notes' => 'Shortage due to coin exchange error.',
    ]);

    $response->assertRedirect();

    $settlement->refresh();
    expect($settlement->status)->toBe(DailySettlement::STATUS_CLOSED);
    expect((float) $settlement->actual_cash)->toBe(1380.00);
    expect((float) $settlement->discrepancy)->toBe(-20.00); // 1380 - 1400 = -20
    expect($settlement->closed_by_user_id)->toBe($user->id);
    expect($settlement->closed_at)->not->toBeNull();
    expect($settlement->notes)->toBe('Shortage due to coin exchange error.');
});

test('user can reopen a closed daily register', function () {
    $user = User::factory()->create();
    $today = '2026-09-07';

    $settlement = DailySettlement::query()->create([
        'settlement_date' => $today,
        'user_id' => $user->id,
        'closed_by_user_id' => $user->id,
        'status' => DailySettlement::STATUS_CLOSED,
        'opening_cash' => 1000.00,
        'cash_in' => 200.00,
        'cash_out' => 0,
        'expected_cash' => 1200.00,
        'actual_cash' => 1200.00,
        'discrepancy' => 0.00,
        'closed_at' => now(),
    ]);

    $response = $this->actingAs($user)->put(route('reports.settlements.reopen', $settlement));
    $response->assertRedirect();

    $settlement->refresh();
    expect($settlement->status)->toBe(DailySettlement::STATUS_OPEN);
    expect($settlement->closed_by_user_id)->toBeNull();
    expect($settlement->closed_at)->toBeNull();
});

test('user can view settlement slip details via API', function () {
    $user = User::factory()->create();
    $today = '2026-09-07';

    $settlement = DailySettlement::query()->create([
        'settlement_date' => $today,
        'user_id' => $user->id,
        'status' => DailySettlement::STATUS_OPEN,
        'opening_cash' => 500.00,
        'cash_in' => 0,
        'cash_out' => 0,
        'expected_cash' => 500.00,
    ]);

    Transaction::query()->create([
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => $today,
        'customer_name' => 'Alice',
        'amount' => 150.00,
        'ddn_amount' => 150.00,
    ]);

    $response = $this->actingAs($user)->get(route('reports.settlements.show', $settlement));
    $response->assertOk();

    $data = $response->json();
    expect($data)->toHaveKeys(['settlement', 'transactions', 'expenses']);
    expect($data['transactions'])->toHaveCount(1);
    expect($data['transactions'][0]['customer_name'])->toBe('Alice');
});

test('financial report calculates accurate metrics and breakdowns for date range', function () {
    $user = User::factory()->create();
    $now = Carbon::create(2026, 9, 15, 12, 0, 0);
    Carbon::setTestNow($now);

    // Airline cash transaction
    Transaction::query()->create([
        'type' => Transaction::TYPE_AIRLINE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-10',
        'customer_name' => 'John Travel',
        'amount' => 10000.00,
        'third_party_payment' => 8000.00,
        'ddn_amount' => 2000.00,
    ]);

    // Translation GCash transaction
    Transaction::query()->create([
        'type' => Transaction::TYPE_TRANSLATION,
        'payment_method' => Transaction::PAYMENT_METHOD_GCASH,
        'transaction_date' => '2026-09-12',
        'customer_name' => 'Maria Doc',
        'amount' => 3000.00,
        'third_party_payment' => 1000.00,
        'ddn_amount' => 2000.00,
    ]);

    // Expense
    $cat = ExpenseCategory::query()->create(['name' => 'Internet & Utilities']);
    Expense::query()->create([
        'expense_category_id' => $cat->id,
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'expense_date' => '2026-09-11',
        'title' => 'Fiber bill',
        'amount' => 1500.00,
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['period' => 'this_month']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->where('financialReports.summary.total_sales', 13000)
            ->where('financialReports.summary.total_ddn', 4000)
            ->where('financialReports.summary.total_expenses', 1500)
            ->where('financialReports.summary.net_profit', 2500) // 4000 - 1500
            ->where('financialReports.summary.cash_sales', 10000)
            ->where('financialReports.summary.gcash_sales', 3000)
        );
});

test('financial report accounts for split payments and excludes personal expenses', function () {
    $user = User::factory()->create();
    $now = Carbon::create(2026, 9, 15, 12, 0, 0);
    Carbon::setTestNow($now);

    // Split payment transaction: 5,000 total (2,000 Cash + 3,000 GCash)
    Transaction::query()->create([
        'type' => Transaction::TYPE_AIRLINE,
        'payment_method' => Transaction::PAYMENT_METHOD_SPLIT,
        'transaction_date' => '2026-09-10',
        'customer_name' => 'Split Pay Customer',
        'amount' => 5000.00,
        'cash_amount' => 2000.00,
        'gcash_amount' => 3000.00,
        'ddn_amount' => 1500.00,
    ]);

    // Ferry transaction
    Transaction::query()->create([
        'type' => Transaction::TYPE_FERRY,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-11',
        'customer_name' => 'Ferry Passenger',
        'amount' => 1200.00,
        'ddn_amount' => 300.00,
    ]);

    // Remittance transaction
    Transaction::query()->create([
        'type' => Transaction::TYPE_REMITTANCE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-12',
        'customer_name' => 'Remittance Sender',
        'amount' => 1000.00,
        'ddn_amount' => 50.00,
    ]);

    $businessCat = ExpenseCategory::query()->create(['name' => 'Office Rent']);
    $personalCat = ExpenseCategory::query()->create(['name' => 'Personal Errand']);

    // Business expense
    Expense::query()->create([
        'expense_category_id' => $businessCat->id,
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'expense_date' => '2026-09-11',
        'title' => 'Office Lease',
        'amount' => 500.00,
        'is_personal' => false,
    ]);

    // Personal expense (should be excluded)
    Expense::query()->create([
        'expense_category_id' => $personalCat->id,
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'expense_date' => '2026-09-11',
        'title' => 'Personal dinner',
        'amount' => 800.00,
        'is_personal' => true,
        'reimbursement_status' => Expense::REIMBURSEMENT_STATUS_PENDING,
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['period' => 'this_month']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->where('financialReports.summary.total_sales', 7200) // 5000 + 1200 + 1000
            ->where('financialReports.summary.total_ddn', 1850) // 1500 + 300 + 50
            ->where('financialReports.summary.cash_sales', 4200) // 2000 (split) + 1200 (ferry) + 1000 (remittance)
            ->where('financialReports.summary.gcash_sales', 3000) // 3000 (split)
            ->where('financialReports.summary.total_expenses', 500) // Excludes 800 personal
            ->where('financialReports.summary.operating_expenses', 500)
            ->where('financialReports.summary.net_profit', 1350) // 1850 - 500
            ->has('financialReports.servicesBreakdown', 7) // All 7 service types
            ->has('financialReports.expensesBreakdown', 1) // Only Office Rent
            ->where('financialReports.expensesBreakdown.0.category', 'Office Rent')
        );

    Carbon::setTestNow();
});

test('user can export financial report as CSV', function () {
    $user = User::factory()->create();
    $now = Carbon::create(2026, 9, 15, 12, 0, 0);
    Carbon::setTestNow($now);

    Transaction::query()->create([
        'type' => Transaction::TYPE_AIRLINE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-10',
        'customer_name' => 'CSV Customer',
        'amount' => 5000.00,
        'ddn_amount' => 1000.00,
    ]);

    $response = $this->actingAs($user)->get(route('reports.export', ['period' => 'this_month']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    // Capture streamed response content
    ob_start();
    $response->sendContent();
    $csvContent = ob_get_clean();

    expect($csvContent)->toContain('DDN RECORD SYSTEM - FINANCIAL REPORT');
    expect($csvContent)->toContain('FINANCIAL SUMMARY');
    expect($csvContent)->toContain('Total Sales (Gross Payments)');
    expect($csvContent)->toContain('SERVICE BREAKDOWN');
    expect($csvContent)->toContain('Ferry');
    expect($csvContent)->toContain('Remittance');
    expect($csvContent)->toContain('EXPENSES BREAKDOWN BY CATEGORY');
});

test('user can close an old open settlement record from previous dates', function () {
    $user = User::factory()->create();
    $oldDate = '2026-08-15';

    // Create a transaction on that date so syncMetricsWithRecords calculates cash_in
    Transaction::query()->create([
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => $oldDate,
        'customer_name' => 'Past Customer',
        'amount' => 1000.00,
        'ddn_amount' => 1000.00,
    ]);

    // Create an old open settlement
    $settlement = DailySettlement::query()->create([
        'settlement_date' => $oldDate,
        'user_id' => $user->id,
        'status' => DailySettlement::STATUS_OPEN,
        'opening_cash' => 2000.00,
        'cash_in' => 1000.00,
        'cash_out' => 0.00,
        'expected_cash' => 3000.00,
        'total_sales' => 1000.00,
        'total_ddn_earnings' => 1000.00,
        'opened_at' => Carbon::parse($oldDate),
    ]);

    $response = $this->actingAs($user)->put(route('reports.settlements.close', $settlement), [
        'actual_cash' => 2950.00,
        'notes' => 'Old record closed by admin. ₱50 short.',
    ]);

    $response->assertRedirect();

    $settlement->refresh();
    expect($settlement->status)->toBe(DailySettlement::STATUS_CLOSED);
    expect((float) $settlement->actual_cash)->toBe(2950.00);
    expect((float) $settlement->discrepancy)->toBe(-50.00);
    expect($settlement->closed_by_user_id)->toBe($user->id);
    expect($settlement->notes)->toBe('Old record closed by admin. ₱50 short.');
    expect($settlement->closed_at)->not->toBeNull();
});

test('admin can edit an existing cash settlement record', function () {
    $user = User::factory()->create();
    $date = '2026-08-20';

    $settlement = DailySettlement::query()->create([
        'settlement_date' => $date,
        'user_id' => $user->id,
        'status' => DailySettlement::STATUS_CLOSED,
        'opening_cash' => 1000.00,
        'cash_in' => 2000.00,
        'cash_out' => 500.00,
        'expected_cash' => 2500.00,
        'actual_cash' => 2500.00,
        'discrepancy' => 0.00,
        'total_sales' => 2000.00,
        'total_ddn_earnings' => 1000.00,
        'opened_at' => Carbon::parse($date),
        'closed_at' => Carbon::parse($date),
    ]);

    // Admin edits opening float and actual cash count
    $response = $this->actingAs($user)->put(route('reports.settlements.update', $settlement), [
        'opening_cash' => 1500.00,
        'actual_cash' => 3000.00,
        'status' => DailySettlement::STATUS_CLOSED,
        'notes' => 'Adjusted opening cash float from ₱1,000 to ₱1,500.',
        'sync_metrics' => false,
    ]);

    $response->assertRedirect();

    $settlement->refresh();
    expect((float) $settlement->opening_cash)->toBe(1500.00);
    expect((float) $settlement->expected_cash)->toBe(3000.00); // 1500 + 2000 - 500
    expect((float) $settlement->actual_cash)->toBe(3000.00);
    expect((float) $settlement->discrepancy)->toBe(0.00);
    expect($settlement->notes)->toBe('Adjusted opening cash float from ₱1,000 to ₱1,500.');
});

test('updating a settlement validates inputs', function () {
    $user = User::factory()->create();
    $settlement = DailySettlement::query()->create([
        'settlement_date' => '2026-08-25',
        'status' => DailySettlement::STATUS_OPEN,
        'opening_cash' => 1000.00,
        'cash_in' => 0.00,
        'cash_out' => 0.00,
        'expected_cash' => 1000.00,
    ]);

    $response = $this->actingAs($user)->put(route('reports.settlements.update', $settlement), [
        'opening_cash' => -50,
        'status' => 'invalid_status',
    ]);

    $response->assertSessionHasErrors(['opening_cash', 'status']);
});

test('user can preview past settlement metrics and opening balance suggestions', function () {
    $user = User::factory()->create();
    $priorDate = '2026-09-01';
    $targetDate = '2026-09-05';
    Carbon::setTestNow(Carbon::parse('2026-09-10'));

    // Create a prior closed settlement to verify suggested opening balances
    DailySettlement::query()->create([
        'settlement_date' => $priorDate,
        'user_id' => $user->id,
        'closed_by_user_id' => $user->id,
        'status' => DailySettlement::STATUS_CLOSED,
        'opening_cash' => 1000.00,
        'cash_in' => 500.00,
        'cash_out' => 100.00,
        'expected_cash' => 1400.00,
        'actual_cash' => 1450.00,
        'opening_gcash' => 2000.00,
        'actual_gcash' => 2300.00,
        'closed_at' => now(),
    ]);

    // Create transaction on target date
    Transaction::query()->create([
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => $targetDate,
        'customer_name' => 'Past Customer',
        'amount' => 750.00,
        'ddn_amount' => 750.00,
    ]);

    // Create expense on target date
    $cat = ExpenseCategory::query()->create(['name' => 'Utilities']);
    Expense::query()->create([
        'expense_category_id' => $cat->id,
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'expense_date' => $targetDate,
        'title' => 'Courier',
        'amount' => 150.00,
    ]);

    $response = $this->actingAs($user)->get(route('reports.settlements.preview', ['date' => $targetDate]));
    $response->assertOk();

    $data = $response->json();
    expect($data['exists'])->toBeFalse();
    expect($data['suggested_opening_cash'])->toBe(1450);
    expect($data['suggested_opening_gcash'])->toBe(2300);
    expect((float) $data['metrics']['cash_in'])->toBe(750.00);
    expect((float) $data['metrics']['cash_out'])->toBe(150.00);
});

test('preview returns exists true if settlement already exists for date', function () {
    $user = User::factory()->create();
    $date = '2026-09-05';
    Carbon::setTestNow(Carbon::parse('2026-09-10'));

    DailySettlement::query()->create([
        'settlement_date' => $date,
        'user_id' => $user->id,
        'status' => DailySettlement::STATUS_CLOSED,
        'opening_cash' => 1000.00,
        'cash_in' => 0,
        'cash_out' => 0,
        'expected_cash' => 1000.00,
        'actual_cash' => 1000.00,
    ]);

    $response = $this->actingAs($user)->get(route('reports.settlements.preview', ['date' => $date]));
    $response->assertOk();
    expect($response->json('exists'))->toBeTrue();
});

test('user can record a past settlement directly as closed with computed discrepancies', function () {
    $user = User::factory()->create();
    $targetDate = '2026-09-04';
    Carbon::setTestNow(Carbon::parse('2026-09-10'));

    // Transaction on target date
    Transaction::query()->create([
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => $targetDate,
        'customer_name' => 'Past Walk-in',
        'amount' => 800.00,
        'ddn_amount' => 800.00,
    ]);

    // Expense on target date
    $cat = ExpenseCategory::query()->create(['name' => 'Misc']);
    Expense::query()->create([
        'expense_category_id' => $cat->id,
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'expense_date' => $targetDate,
        'title' => 'Tape',
        'amount' => 100.00,
    ]);

    $response = $this->actingAs($user)->post(route('reports.settlements.store-past'), [
        'settlement_date' => $targetDate,
        'opening_cash' => 1000.00,
        'opening_gcash' => 500.00,
        'actual_cash' => 1650.00, // Expected: 1000 + 800 - 100 = 1700 => -50 shortage
        'actual_gcash' => 500.00, // Expected: 500 + 0 - 0 = 500 => 0 discrepancy
        'notes' => 'Settled retrospective day with minor cash shortage.',
    ]);

    $response->assertRedirect();

    $settlement = DailySettlement::query()->whereDate('settlement_date', $targetDate)->first();
    expect($settlement)->not->toBeNull();
    expect($settlement->status)->toBe(DailySettlement::STATUS_CLOSED);
    expect($settlement->user_id)->toBe($user->id);
    expect($settlement->closed_by_user_id)->toBe($user->id);
    expect($settlement->closed_at)->not->toBeNull();
    expect((float) $settlement->opening_cash)->toBe(1000.00);
    expect((float) $settlement->cash_in)->toBe(800.00);
    expect((float) $settlement->cash_out)->toBe(100.00);
    expect((float) $settlement->expected_cash)->toBe(1700.00);
    expect((float) $settlement->actual_cash)->toBe(1650.00);
    expect((float) $settlement->discrepancy)->toBe(-50.00);
    expect((float) $settlement->opening_gcash)->toBe(500.00);
    expect((float) $settlement->expected_gcash)->toBe(500.00);
    expect((float) $settlement->actual_gcash)->toBe(500.00);
    expect((float) $settlement->gcash_discrepancy)->toBe(0.00);
    expect($settlement->notes)->toBe('Settled retrospective day with minor cash shortage.');
});

test('recording past settlement validates inputs and rejects future dates and duplicates', function () {
    $user = User::factory()->create();
    Carbon::setTestNow(Carbon::parse('2026-09-10'));

    // Reject future date
    $responseFuture = $this->actingAs($user)->post(route('reports.settlements.store-past'), [
        'settlement_date' => '2026-09-15',
        'opening_cash' => 500.00,
        'actual_cash' => 500.00,
    ]);
    $responseFuture->assertSessionHasErrors('settlement_date');

    // Create existing settlement
    DailySettlement::query()->create([
        'settlement_date' => '2026-09-08',
        'user_id' => $user->id,
        'status' => DailySettlement::STATUS_CLOSED,
        'opening_cash' => 500.00,
        'cash_in' => 0,
        'cash_out' => 0,
        'expected_cash' => 500.00,
        'actual_cash' => 500.00,
    ]);

    // Reject duplicate date
    $responseDuplicate = $this->actingAs($user)->post(route('reports.settlements.store-past'), [
        'settlement_date' => '2026-09-08',
        'opening_cash' => 500.00,
        'actual_cash' => 500.00,
    ]);
    $responseDuplicate->assertSessionHasErrors('settlement_date');
});

test('admin can delete a settlement record permanently and free the settlement date', function () {
    $admin = User::factory()->create();
    $date = '2026-09-05';

    $settlement = DailySettlement::query()->create([
        'settlement_date' => $date,
        'user_id' => $admin->id,
        'status' => DailySettlement::STATUS_CLOSED,
        'opening_cash' => 1000.00,
        'cash_in' => 500.00,
        'cash_out' => 100.00,
        'expected_cash' => 1400.00,
        'actual_cash' => 1400.00,
    ]);

    $response = $this->from(route('reports.index'))
        ->actingAs($admin)
        ->delete(route('reports.settlements.destroy', $settlement));

    $response->assertRedirect(route('reports.index'));
    $this->assertDatabaseMissing('daily_settlements', [
        'id' => $settlement->id,
    ]);

    // Verify date is freed and can be re-settled without unique constraint violation
    $recreateResponse = $this->actingAs($admin)->post(route('reports.settlements.store-past'), [
        'settlement_date' => $date,
        'opening_cash' => 1000.00,
        'actual_cash' => 1000.00,
    ]);
    $recreateResponse->assertRedirect();
    $this->assertDatabaseHas('daily_settlements', [
        'settlement_date' => $date,
    ]);
});

test('staff user cannot delete a settlement record', function () {
    $admin = User::factory()->create();
    $staff = User::factory()->staff()->create();
    $date = '2026-09-05';

    $settlement = DailySettlement::query()->create([
        'settlement_date' => $date,
        'user_id' => $admin->id,
        'status' => DailySettlement::STATUS_CLOSED,
        'opening_cash' => 1000.00,
        'cash_in' => 0,
        'cash_out' => 0,
        'expected_cash' => 1000.00,
        'actual_cash' => 1000.00,
    ]);

    $this->actingAs($staff)
        ->delete(route('reports.settlements.destroy', $settlement))
        ->assertForbidden();

    $this->assertDatabaseHas('daily_settlements', [
        'id' => $settlement->id,
    ]);
});

test('daily settlement calculates metrics correctly for gcash overpayment with cash change and fee', function () {
    $date = '2026-09-12';

    // A transaction of 350 PHP paid via GCash, customer sent 1,000 GCash, received 630 Cash change, 20 fee
    Transaction::query()->create([
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_GCASH,
        'transaction_date' => $date,
        'customer_name' => 'Overpaying Customer',
        'amount' => 350.00,
        'amount_paid' => 350.00,
        'ddn_amount' => 350.00,
        'gcash_tendered' => 1000.00,
        'cash_change' => 630.00,
        'change_fee' => 20.00,
        'payment_status' => Transaction::PAYMENT_STATUS_PAID,
    ]);

    $metrics = DailySettlement::calculateMetricsForDate($date);

    // GCash total should reflect the full 1,000 inflow
    expect((float) $metrics['total_gcash'])->toBe(1000.00);
    // Cash out should reflect the 630 cash dispensed to customer
    expect((float) $metrics['cash_out'])->toBe(630.00);
    // Total sales should include transaction amount + change fee = 370
    expect((float) $metrics['total_sales'])->toBe(370.00);
    // DDN earnings should include transaction amount + change fee = 370
    expect((float) $metrics['total_ddn_earnings'])->toBe(370.00);
});

test('daily settlement accounts for employee share as cash out on cash transactions', function () {
    $date = '2026-09-13';

    // Online Assistance transaction: 250 cash, 150 DDN, 100 employee share
    Transaction::query()->create([
        'type' => Transaction::TYPE_ONLINE_ASSISTANCE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => $date,
        'customer_name' => 'Assisted Client',
        'amount' => 250.00,
        'amount_paid' => 250.00,
        'ddn_amount' => 150.00,
        'employee_share' => 100.00,
        'payment_status' => Transaction::PAYMENT_STATUS_PAID,
    ]);

    $metrics = DailySettlement::calculateMetricsForDate($date);

    // Cash In should be 250
    expect((float) $metrics['cash_in'])->toBe(250.00);
    // Cash Out should reflect the 100 employee cut taken from drawer
    expect((float) $metrics['cash_out'])->toBe(100.00);
    // Total expenses should include the 100 employee cut
    expect((float) $metrics['total_expenses'])->toBe(100.00);
    // Total sales is 250
    expect((float) $metrics['total_sales'])->toBe(250.00);
    // DDN earnings is 150
    expect((float) $metrics['total_ddn_earnings'])->toBe(150.00);
});
