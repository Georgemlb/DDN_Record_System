<?php

use App\Models\DailySettlement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FlightPortalDeposit;
use App\Models\GcashLedgerEntry;
use App\Models\OnlineAssistanceType;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\OnlineAssistanceTypeSeeder;
use Database\Seeders\PsaRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        OnlineAssistanceTypeSeeder::class,
        PsaRateSeeder::class,
        ExpenseCategorySeeder::class,
    ]);
});

/**
 * Helper to simulate a complete Day 1 business cycle with all four services.
 *
 * @return array{settlement: DailySettlement, airlineTx: Transaction, category: ExpenseCategory}
 */
function recordCompleteDay1(TestCase $test, User $admin, string $date = '2026-07-01'): array
{
    // 1. Morning Register Setup: ₱5,000 Cash Drawer Float & ₱10,000 GCash Wallet
    $test->actingAs($admin)->post(route('reports.settlements.start'), [
        'settlement_date' => $date,
        'opening_cash' => 5000.00,
        'opening_gcash' => 10000.00,
    ])->assertRedirect();

    $settlement = DailySettlement::whereDate('settlement_date', $date)->firstOrFail();

    // Anchor GCash wallet balance at ₱10,000.00
    $test->actingAs($admin)->post(route('gcash-ledger.adjustment'), [
        'amount' => 10000.00,
        'entry_date' => $date,
        'is_balance_anchor' => true,
        'notes' => 'Day 1 morning GCash wallet balance anchor',
    ])->assertRedirect();

    // 2. Flight Portal Reload: ₱20,000 funded via Bank Transfer
    $test->actingAs($admin)->post(route('portal-deposits.top-up'), [
        'amount' => '20000.00',
        'entry_date' => $date,
        'payment_source' => 'bank_transfer',
        'reference_number' => 'BDO-TRF-9021',
        'notes' => 'Portal load for airline ticketing',
    ])->assertRedirect();

    // 3. Airline Booking: ₱8,000 ticket with ₱7,000 portal deduction & ₱4,000 cash downpayment
    $test->actingAs($admin)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_AIRLINE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => $date,
        'customer_name' => 'Juan Dela Cruz',
        'customer_contact' => '09171234567',
        'pax' => 1,
        'provider' => 'Philippine Airlines',
        'flight_type' => 'local',
        'trip_type' => 'one_way',
        'amount' => '8000.00',
        'third_party_payment' => '7000.00',
        'disbursement_source' => Transaction::DISBURSEMENT_SOURCE_FLIGHT_PORTAL,
        'payment_status' => Transaction::PAYMENT_STATUS_PARTIAL,
        'amount_paid' => '4000.00',
    ])->assertRedirect();

    $airlineTx = Transaction::where('customer_name', 'Juan Dela Cruz')->firstOrFail();

    // 4. Remittance Cash-Out: ₱2,000 Cash paid to customer, ₱2,000 GCash collected (+₱60 fee)
    $test->actingAs($admin)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_REMITTANCE,
        'service_details' => 'GCASH_CASH_OUT',
        'transaction_date' => $date,
        'customer_name' => 'Maria Santos',
        'amount' => '2000.00',
        'third_party_payment' => '60.00', // ₱60 agency fee
        'payment_method' => Transaction::PAYMENT_METHOD_GCASH,
        'payment_status' => Transaction::PAYMENT_STATUS_PAID,
    ])->assertRedirect();

    // 5. Remittance Cash-In: ₱1,500 Cash collected from customer, ₱1,500 GCash sent (-₱50 fee)
    $test->actingAs($admin)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_REMITTANCE,
        'service_details' => 'GCASH_CASH_IN',
        'transaction_date' => $date,
        'customer_name' => 'Pedro Penduko',
        'amount' => '1500.00',
        'third_party_payment' => '50.00', // ₱50 agency fee
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'payment_status' => Transaction::PAYMENT_STATUS_PAID,
    ])->assertRedirect();

    // 6. Online Assistance: ₱600 Cash collected, ₱350 Gov Fee disbursed via GCash
    $onlineType = OnlineAssistanceType::query()->firstOrFail();
    $test->actingAs($admin)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_ONLINE_ASSISTANCE,
        'online_assistance_type_id' => $onlineType->id,
        'transaction_date' => $date,
        'customer_name' => 'Elena Gomez',
        'amount' => '600.00',
        'third_party_payment' => '350.00',
        'disbursement_source' => Transaction::DISBURSEMENT_SOURCE_GCASH,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'payment_status' => Transaction::PAYMENT_STATUS_PAID,
    ])->assertRedirect();

    // 7. PSA Document Service: ₱320 Cash collected (Rate BC: 320), ₱260 PSA fee paid cash
    $test->actingAs($admin)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_PSA,
        'psa_document_type' => 'BC',
        'copies' => 1,
        'transaction_date' => $date,
        'customer_name' => 'Carlos Reyes',
        'amount' => '320.00',
        'third_party_payment' => '260.00',
        'disbursement_source' => Transaction::DISBURSEMENT_SOURCE_CASH,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'payment_status' => Transaction::PAYMENT_STATUS_PAID,
    ])->assertRedirect();

    // 8. Business Expense: ₱300 Office Supplies paid in Cash from Drawer
    $category = ExpenseCategory::query()->firstOrFail();
    $test->actingAs($admin)->post(route('expenses.store'), [
        'title' => 'Office Printer Ink & Thermal Paper',
        'expense_category_id' => $category->id,
        'amount' => '300.00',
        'expense_date' => $date,
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'payee' => 'National Book Store',
        'recurring_frequency' => 'none',
    ])->assertRedirect();

    // 9. Personal Expense Reimbursement: ₱200 Repaid in Cash into Drawer
    $personalExpense = Expense::create([
        'title' => 'Staff Personal Snack Delivery',
        'expense_category_id' => $category->id,
        'amount' => 200.00,
        'expense_date' => '2026-06-30',
        'payment_method' => Expense::PAYMENT_METHOD_GCASH,
        'payee' => 'FoodPanda',
        'is_personal' => true,
        'reimbursement_status' => Expense::REIMBURSEMENT_STATUS_PENDING,
        'user_id' => $admin->id,
    ]);

    $test->actingAs($admin)->post(route('expenses.reimburse', $personalExpense), [
        'reimbursement_method' => Expense::PAYMENT_METHOD_CASH,
        'reimbursed_at' => $date,
        'reimbursement_notes' => 'Staff member paid cash back to drawer',
    ])->assertRedirect();

    // 10. Close Day 1 with actual cash ₱8,960.00 and actual GCash ₱10,150.00
    $settlement->syncMetricsWithRecords();
    $test->actingAs($admin)->put(route('reports.settlements.close', $settlement), [
        'actual_cash' => '8960.00',
        'actual_gcash' => '10150.00',
        'notes' => 'Perfect balance close. 0 discrepancy.',
    ])->assertRedirect();

    return [
        'settlement' => $settlement->fresh(),
        'airlineTx' => $airlineTx->fresh(),
        'category' => $category,
    ];
}

test('full day 1 multi-service business lifecycle and day-end closing reconciliation', function () {
    $admin = User::factory()->admin()->create();
    $day1Date = '2026-07-01';

    $records = recordCompleteDay1($this, $admin, $day1Date);
    $settlement = $records['settlement'];
    $airlineTx = $records['airlineTx'];

    // Assert Airline booking state
    expect((float) $airlineTx->amount)->toBe(8000.00);
    expect((float) $airlineTx->amount_paid)->toBe(4000.00);
    expect((float) $airlineTx->balance)->toBe(4000.00);
    expect($airlineTx->payment_status)->toBe(Transaction::PAYMENT_STATUS_PARTIAL);

    // Flight portal running balance is ₱20,000 - ₱7,000 = ₱13,000
    expect(FlightPortalDeposit::getCurrentBalance())->toBe(13000.00);

    // GCash ledger running balance is ₱10,150.00
    expect(GcashLedgerEntry::getCurrentBalance())->toBe(10150.00);

    // Assert Settlement metrics
    // Cash In:
    // Airline downpayment (4,000) + Remittance cash-in (1,500) + Online assistance (600) + PSA (320) + Personal reimb (200) = 6,620.00
    //
    // Cash Out:
    // Remittance cash-out (2,000) + Online assistance employee cut (100) + PSA fee (260) + Office supplies (300) = 2,660.00
    //
    // Expected Drawer Cash:
    // Opening 5,000 + 6,620 (In) - 2,660 (Out) = ₱8,960.00
    $metrics = DailySettlement::calculateMetricsForDate($day1Date);
    expect($metrics['cash_in'])->toBe(6620.00);
    expect($metrics['cash_out'])->toBe(2660.00);
    expect($metrics['gcash_in'])->toBe(2000.00);
    expect($metrics['gcash_out'])->toBe(1850.00);

    expect($settlement->status)->toBe(DailySettlement::STATUS_CLOSED);
    expect((float) $settlement->expected_cash)->toBe(8960.00);
    expect((float) $settlement->actual_cash)->toBe(8960.00);
    expect((float) $settlement->discrepancy)->toBe(0.00);
    expect((float) $settlement->expected_gcash)->toBe(10150.00);
    expect((float) $settlement->actual_gcash)->toBe(10150.00);
    expect((float) $settlement->gcash_discrepancy)->toBe(0.00);

    // Audit command on Day 1 returns exit code 0 and 0 drift
    $this->artisan("ddn:verify-balances --date={$day1Date}")
        ->expectsOutputToContain('All financial balances and ledger entries are strictly balanced with 0 drift.')
        ->assertSuccessful();
});

test('day 2 roll-over, suggested float defaults and multi-day balance collection', function () {
    $admin = User::factory()->admin()->create();

    $day1Date = '2026-07-01';
    $day2Date = '2026-07-02';

    // Seed realistic Day 1 operations
    $records = recordCompleteDay1($this, $admin, $day1Date);
    $airlineTx = $records['airlineTx'];

    // -------------------------------------------------------------------------
    // 1. Day 2 Morning: Preview Past Settlement Suggests Day 1 Actual Float
    // -------------------------------------------------------------------------
    $previewResponse = $this->actingAs($admin)->getJson(route('reports.settlements.preview', ['date' => $day2Date]));
    $previewResponse->assertOk()
        ->assertJson([
            'exists' => false,
            'suggested_opening_cash' => 8960.00,
            'suggested_opening_gcash' => 10150.00,
        ]);

    // Start Day 2 with suggested floats
    $startDay2Response = $this->actingAs($admin)->post(route('reports.settlements.start'), [
        'settlement_date' => $day2Date,
        'opening_cash' => 8960.00,
        'opening_gcash' => 10150.00,
    ]);
    $startDay2Response->assertRedirect();

    $day2Settlement = DailySettlement::whereDate('settlement_date', $day2Date)->firstOrFail();
    expect((float) $day2Settlement->opening_cash)->toBe(8960.00);
    expect((float) $day2Settlement->opening_gcash)->toBe(10150.00);

    // -------------------------------------------------------------------------
    // 2. Collect Remaining ₱4,000 Balance on Day 2 via Split Payment (₱2k Cash + ₱2k GCash)
    // -------------------------------------------------------------------------
    $paymentResponse = $this->actingAs($admin)->post(route('transactions.payments.store', $airlineTx->id), [
        'amount' => '4000.00',
        'payment_method' => Transaction::PAYMENT_METHOD_SPLIT,
        'cash_amount' => '2000.00',
        'gcash_amount' => '2000.00',
        'payment_date' => $day2Date,
        'notes' => 'Ticket pick-up balance settled with split payment',
    ]);
    $paymentResponse->assertRedirect();

    // Transaction balance assertions
    $airlineTx->refresh();
    expect((float) $airlineTx->amount_paid)->toBe(8000.00);
    expect((float) $airlineTx->balance)->toBe(0.00);
    expect($airlineTx->payment_status)->toBe(Transaction::PAYMENT_STATUS_PAID);
    expect($airlineTx->payments)->toHaveCount(2);

    // Day 2 Settlement metrics received the ₱2,000 cash and ₱2,000 gcash
    $day2Metrics = DailySettlement::calculateMetricsForDate($day2Date);
    expect($day2Metrics['cash_in'])->toBe(2000.00);
    expect($day2Metrics['gcash_in'])->toBe(2000.00);
    expect($day2Metrics['total_sales'])->toBe(0.00); // Does NOT inflate Day 2 sales (originated on Day 1)

    $day2Settlement->syncMetricsWithRecords();
    expect((float) $day2Settlement->expected_cash)->toBe(10960.00); // 8960 opening + 2000 cash_in
    expect((float) $day2Settlement->expected_gcash)->toBe(12150.00); // 10150 opening + 2000 gcash_in

    // Day 1 Settlement remains untouched and intact
    $day1SettlementFresh = DailySettlement::whereDate('settlement_date', $day1Date)->firstOrFail();
    expect((float) $day1SettlementFresh->cash_in)->toBe(6620.00);
    expect($day1SettlementFresh->status)->toBe(DailySettlement::STATUS_CLOSED);

    // GCash Ledger Entry created on Day 2
    $day2GcashEntry = GcashLedgerEntry::whereDate('entry_date', $day2Date)->firstOrFail();
    expect($day2GcashEntry->type)->toBe(GcashLedgerEntry::TYPE_CUSTOMER_PAYMENT);
    expect((float) $day2GcashEntry->amount)->toBe(2000.00);
    expect(GcashLedgerEntry::getCurrentBalance())->toBe(12150.00);

    // -------------------------------------------------------------------------
    // 3. Audit Full System across both days
    // -------------------------------------------------------------------------
    $this->artisan('ddn:verify-balances')
        ->expectsOutputToContain('All financial balances and ledger entries are strictly balanced with 0 drift.')
        ->assertSuccessful();
});

test('reopen closed day 1, late expense adjustments and automated re-synchronization', function () {
    $admin = User::factory()->admin()->create();

    $day1Date = '2026-07-01';

    // Seed realistic Day 1 operations
    $records = recordCompleteDay1($this, $admin, $day1Date);
    $settlement = $records['settlement'];
    $category = $records['category'];

    // 1. Admin reopens Day 1
    $reopenResponse = $this->actingAs($admin)->put(route('reports.settlements.reopen', $settlement));
    $reopenResponse->assertRedirect();

    $settlement->refresh();
    expect($settlement->status)->toBe(DailySettlement::STATUS_OPEN);
    expect($settlement->closed_by_user_id)->toBeNull();

    // 2. Add missed cash expense of ₱100 on Day 1
    $expenseResponse = $this->actingAs($admin)->post(route('expenses.store'), [
        'title' => 'Emergency Motorcycle Delivery',
        'expense_category_id' => $category->id,
        'amount' => '100.00',
        'expense_date' => $day1Date,
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'payee' => 'Grab Express',
        'recurring_frequency' => 'none',
    ]);
    $expenseResponse->assertRedirect();

    // Settlement cash_out should increase from 2,660 to 2,760 and expected_cash adjust from 8,960 to 8,860
    $settlement->refresh();
    expect((float) $settlement->cash_out)->toBe(2760.00);
    expect((float) $settlement->expected_cash)->toBe(8860.00);

    // 3. Re-close Day 1 with updated count of ₱8,860
    $recloseResponse = $this->actingAs($admin)->put(route('reports.settlements.close', $settlement), [
        'actual_cash' => '8860.00',
        'actual_gcash' => '10150.00',
        'notes' => 'Reclosed after adding missed motorcycle delivery expense',
    ]);
    $recloseResponse->assertRedirect();

    $settlement->refresh();
    expect($settlement->status)->toBe(DailySettlement::STATUS_CLOSED);
    expect((float) $settlement->actual_cash)->toBe(8860.00);
    expect((float) $settlement->discrepancy)->toBe(0.00);

    // 4. View slip data endpoint
    $slipResponse = $this->actingAs($admin)->get(route('reports.settlements.show', $settlement));
    $slipResponse->assertOk();
    $slipData = $slipResponse->json();
    expect((float) $slipData['settlement']['actual_cash'])->toBe(8860.00);
    expect((float) $slipData['settlement']['discrepancy'])->toBe(0.00);

    // 5. Audit passes with 0 drift
    $this->artisan('ddn:verify-balances')
        ->expectsOutputToContain('All financial balances and ledger entries are strictly balanced with 0 drift.')
        ->assertSuccessful();
});
