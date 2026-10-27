<?php

use App\Models\DailySettlement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GcashLedgerEntry;
use App\Models\OnlineAssistanceType;
use App\Models\PsaRate;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('guests are redirected to login when accessing gcash ledger', function () {
    $this->get(route('gcash-ledger.index'))->assertRedirect(route('login'));
    $this->post(route('gcash-ledger.adjustment'))->assertRedirect(route('login'));
});

test('authenticated users can view gcash ledger index with metrics', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('gcash-ledger.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gcash-ledger/Index')
            ->has('entries')
            ->has('metrics')
            ->where('metrics.current_balance', 0)
        );
});

test('user can record a manual gcash balance adjustment with anchor', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('gcash-ledger.adjustment'), [
            'amount' => 15000.00,
            'entry_date' => '2026-09-09',
            'notes' => 'Opening verified balance',
            'is_balance_anchor' => true,
            'target_balance' => 15000.00,
        ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('gcash_ledger_entries', [
        'type' => GcashLedgerEntry::TYPE_ADJUSTMENT,
        'amount' => 15000.00,
        'running_balance' => 15000.00,
        'is_balance_anchor' => true,
    ]);

    expect(GcashLedgerEntry::getCurrentBalance())->toEqual(15000.00);
});

test('recording an expense with gcash payment method automatically syncs to gcash ledger', function () {
    $user = User::factory()->create();
    $cat = ExpenseCategory::query()->create(['name' => 'Utilities']);

    $response = $this->actingAs($user)
        ->post(route('expenses.store'), [
            'expense_date' => '2026-09-09',
            'title' => 'Office Internet bill',
            'expense_category_id' => $cat->id,
            'amount' => 1500.00,
            'payment_method' => Expense::PAYMENT_METHOD_GCASH,
            'reference_number' => 'GC-EXP-001',
            'disbursement_source' => 'gcash',
            'notes' => 'Paid via GCash app',
        ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('gcash_ledger_entries', [
        'type' => GcashLedgerEntry::TYPE_EXPENSE_PAYMENT,
        'amount' => 1500.00,
    ]);

    expect(GcashLedgerEntry::getCurrentBalance())->toEqual(-1500.00);
});

test('online assistance transaction calculates 60/40 net split and syncs government fee to gcash ledger', function () {
    $user = User::factory()->create();

    $type = OnlineAssistanceType::create([
        'name' => 'National Police Clearance',
        'price' => 280.00,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => Transaction::TYPE_ONLINE_ASSISTANCE,
            'online_assistance_type_id' => $type->id,
            'transaction_date' => '2026-09-09',
            'customer_name' => 'Juan Dela Cruz',
            'amount' => 280.00,
            'third_party_payment' => 180.00,
            'disbursement_source' => Transaction::DISBURSEMENT_SOURCE_GCASH,
            'payment_method' => Transaction::PAYMENT_METHOD_CASH, // Customer paid cash in drawer
            'payment_status' => Transaction::PAYMENT_STATUS_PAID,
        ]);

    $response->assertSessionHasNoErrors();

    // Verify 60/40 split on net margin: (280 - 180) = 100 margin -> DDN: 60, Employee: 40
    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_ONLINE_ASSISTANCE,
        'customer_name' => 'Juan Dela Cruz',
        'amount' => 280.00,
        'third_party_payment' => 180.00,
        'ddn_amount' => 60.00,
        'employee_share' => 40.00,
        'disbursement_source' => Transaction::DISBURSEMENT_SOURCE_GCASH,
    ]);

    // Verify GCash ledger entry created for 180 government fee deduction
    $this->assertDatabaseHas('gcash_ledger_entries', [
        'type' => GcashLedgerEntry::TYPE_GOVERNMENT_DISBURSEMENT,
        'amount' => 180.00,
    ]);
});

test('gcash remittance cash-out simultaneously affects drawer cash and gcash wallet', function () {
    $user = User::factory()->create();

    // Customer withdraws 350 cash: sends 350 GCash (+GCash), agency dispenses 350 cash (-Cash)
    $response = $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => Transaction::TYPE_REMITTANCE,
            'service_details' => 'GCASH_CASH_OUT',
            'transaction_date' => '2026-09-09',
            'customer_name' => 'Maria Santos',
            'amount' => 350.00,
            'third_party_payment' => 10.00, // 10 fee
            'payment_method' => Transaction::PAYMENT_METHOD_GCASH,
            'payment_status' => Transaction::PAYMENT_STATUS_PAID,
        ]);

    $response->assertSessionHasNoErrors();

    // Check transaction created with DDN earnings = 10
    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_REMITTANCE,
        'service_details' => 'GCASH_CASH_OUT',
        'amount' => 350.00,
        'ddn_amount' => 10.00,
    ]);

    // Check GCash Ledger entry: Inflow of 350
    $this->assertDatabaseHas('gcash_ledger_entries', [
        'type' => GcashLedgerEntry::TYPE_REMITTANCE_CASH_OUT,
        'amount' => 350.00,
    ]);

    // Check Daily Settlement calculations
    $metrics = DailySettlement::calculateMetricsForDate('2026-09-09');
    expect((float) $metrics['cash_out'])->toEqual(350.00) // Dispensed from drawer
        ->and((float) $metrics['gcash_in'])->toEqual(350.00) // Collected in GCash
        ->and((float) $metrics['total_ddn_earnings'])->toEqual(10.00);
});

test('gcash remittance cash-in simultaneously affects drawer cash and gcash wallet', function () {
    $user = User::factory()->create();

    // Customer deposits 500 cash: pays 500 cash (+Cash), agency sends 500 GCash (-GCash)
    $response = $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => Transaction::TYPE_REMITTANCE,
            'service_details' => 'GCASH_CASH_IN',
            'transaction_date' => '2026-09-09',
            'customer_name' => 'Pedro Penduko',
            'amount' => 500.00,
            'third_party_payment' => 15.00, // 15 fee
            'payment_method' => Transaction::PAYMENT_METHOD_CASH,
            'payment_status' => Transaction::PAYMENT_STATUS_PAID,
        ]);

    $response->assertSessionHasNoErrors();

    // Check transaction created with DDN earnings = 15
    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_REMITTANCE,
        'service_details' => 'GCASH_CASH_IN',
        'amount' => 500.00,
        'ddn_amount' => 15.00,
    ]);

    // Check GCash Ledger entry: Outflow of 500
    $this->assertDatabaseHas('gcash_ledger_entries', [
        'type' => GcashLedgerEntry::TYPE_REMITTANCE_CASH_IN,
        'amount' => 500.00,
    ]);

    // Check Daily Settlement calculations
    $metrics = DailySettlement::calculateMetricsForDate('2026-09-09');
    expect((float) $metrics['cash_in'])->toEqual(500.00) // Physical cash received into drawer
        ->and((float) $metrics['gcash_out'])->toEqual(500.00) // GCash sent from wallet
        ->and((float) $metrics['total_ddn_earnings'])->toEqual(15.00);
});

test('daily settlement can reconcile gcash opening, actual, and discrepancy', function () {
    $user = User::factory()->create();

    // Start register with both opening cash and opening gcash
    $response = $this->actingAs($user)
        ->post(route('reports.settlements.start'), [
            'settlement_date' => '2026-09-09',
            'opening_cash' => 2000.00,
            'opening_gcash' => 5000.00,
            'notes' => 'Start with 2k drawer and 5k GCash',
        ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('daily_settlements', [
        'settlement_date' => '2026-09-09',
        'opening_cash' => 2000.00,
        'opening_gcash' => 5000.00,
        'status' => DailySettlement::STATUS_OPEN,
    ]);

    $settlement = DailySettlement::whereDate('settlement_date', '2026-09-09')->first();

    // Close register with actual gcash counted
    $closeResponse = $this->actingAs($user)
        ->put(route('reports.settlements.close', $settlement), [
            'actual_cash' => 2000.00,
            'actual_gcash' => 5000.00,
            'notes' => 'All balanced',
        ]);

    $closeResponse->assertSessionHasNoErrors();

    $settlement->refresh();
    expect($settlement->status)->toEqual(DailySettlement::STATUS_CLOSED)
        ->and((float) $settlement->actual_gcash)->toEqual(5000.00)
        ->and((float) $settlement->gcash_discrepancy)->toEqual(0.00);
});

test('online assistance without disbursement does not deduct government fee from gcash ledger', function () {
    $user = User::factory()->create();

    $type = OnlineAssistanceType::create([
        'name' => 'Online Form Assistance',
        'price' => 300.00,
        'has_disbursement' => false,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => Transaction::TYPE_ONLINE_ASSISTANCE,
            'online_assistance_type_id' => $type->id,
            'transaction_date' => '2026-09-09',
            'customer_name' => 'Maria Clara',
            'amount' => 300.00,
            'payment_method' => Transaction::PAYMENT_METHOD_CASH,
            'payment_status' => Transaction::PAYMENT_STATUS_PAID,
        ]);

    $response->assertSessionHasNoErrors();

    // 0 disbursement -> full 300 margin -> DDN: 180 (60%), Employee: 120 (40%)
    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_ONLINE_ASSISTANCE,
        'customer_name' => 'Maria Clara',
        'amount' => 300.00,
        'third_party_payment' => null,
        'ddn_amount' => 180.00,
        'employee_share' => 120.00,
    ]);

    // Ensure NO government disbursement entry was created
    $this->assertDatabaseMissing('gcash_ledger_entries', [
        'type' => GcashLedgerEntry::TYPE_GOVERNMENT_DISBURSEMENT,
    ]);
});

test('gcash adjustment funded from cash drawer creates cash drawer expense', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('gcash-ledger.adjustment'), [
            'amount' => 500.00,
            'entry_date' => '2026-09-09',
            'notes' => 'Reload wallet from register float',
            'from_drawer' => true,
        ]);

    $response->assertSessionHasNoErrors();

    // Verify Expense created for cash drawer
    $this->assertDatabaseHas('expenses', [
        'title' => 'GCash Top-Up from Drawer',
        'amount' => 500.00,
        'payment_method' => 'cash',
        'expense_date' => '2026-09-09',
    ]);

    // Verify GCash ledger entry created
    $this->assertDatabaseHas('gcash_ledger_entries', [
        'type' => GcashLedgerEntry::TYPE_ADJUSTMENT,
        'amount' => 500.00,
        'entry_date' => '2026-09-09',
    ]);
});

test('psa transaction defaults disbursement to cash and does not deduct from gcash ledger', function () {
    $user = User::factory()->create();

    PsaRate::create([
        'document_type' => 'BC',
        'name' => 'Birth Certificate',
        'price' => 320.00,
        'officer_fee' => 260.00,
    ]);

    $response = $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => Transaction::TYPE_PSA,
            'psa_document_type' => 'BC',
            'transaction_date' => '2026-01-05',
            'customer_name' => 'Tarroza Chasler Test',
            'amount' => 320.00,
            'payment_method' => Transaction::PAYMENT_METHOD_CASH,
            'payment_status' => Transaction::PAYMENT_STATUS_PAID,
            // Even if client accidentally sends gcash disbursement source:
            'disbursement_source' => Transaction::DISBURSEMENT_SOURCE_GCASH,
        ]);

    $response->assertSessionHasNoErrors();

    // Verify transaction forced disbursement_source to cash
    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_PSA,
        'customer_name' => 'Tarroza Chasler Test',
        'disbursement_source' => Transaction::DISBURSEMENT_SOURCE_CASH,
        'third_party_payment' => 260.00,
        'ddn_amount' => 60.00,
    ]);

    // Ensure NO government disbursement entry was created in GCash ledger
    $this->assertDatabaseMissing('gcash_ledger_entries', [
        'type' => GcashLedgerEntry::TYPE_GOVERNMENT_DISBURSEMENT,
    ]);
});

test('translation transaction with cash disbursement does not deduct from gcash ledger', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => Transaction::TYPE_TRANSLATION,
            'transaction_date' => '2026-09-09',
            'customer_name' => 'Translation Client Cash',
            'amount' => 500.00,
            'third_party_payment' => 200.00,
            'disbursement_source' => Transaction::DISBURSEMENT_SOURCE_CASH,
            'payment_method' => Transaction::PAYMENT_METHOD_CASH,
            'payment_status' => Transaction::PAYMENT_STATUS_PAID,
        ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_TRANSLATION,
        'customer_name' => 'Translation Client Cash',
        'disbursement_source' => Transaction::DISBURSEMENT_SOURCE_CASH,
        'third_party_payment' => 200.00,
        'ddn_amount' => 300.00,
    ]);

    $this->assertDatabaseMissing('gcash_ledger_entries', [
        'type' => GcashLedgerEntry::TYPE_GOVERNMENT_DISBURSEMENT,
    ]);
});

test('translation transaction with gcash disbursement records translator fee outflow in gcash ledger', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => Transaction::TYPE_TRANSLATION,
            'transaction_date' => '2026-09-09',
            'customer_name' => 'Translation Client GCash',
            'amount' => 500.00,
            'third_party_payment' => 200.00,
            'disbursement_source' => Transaction::DISBURSEMENT_SOURCE_GCASH,
            'payment_method' => Transaction::PAYMENT_METHOD_CASH,
            'payment_status' => Transaction::PAYMENT_STATUS_PAID,
        ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'type' => Transaction::TYPE_TRANSLATION,
        'customer_name' => 'Translation Client GCash',
        'disbursement_source' => Transaction::DISBURSEMENT_SOURCE_GCASH,
        'third_party_payment' => 200.00,
        'ddn_amount' => 300.00,
    ]);

    $this->assertDatabaseHas('gcash_ledger_entries', [
        'type' => GcashLedgerEntry::TYPE_GOVERNMENT_DISBURSEMENT,
        'amount' => 200.00,
        'notes' => 'Translator fee disbursement for Translation Client GCash',
    ]);
});

test('subsequent balance collection via gcash syncs accurately to gcash ledger on payment date', function () {
    $user = User::factory()->create();

    // 1. Initial transaction created with cash downpayment of 500 out of 1,500 total
    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Downpayment Client',
        'amount' => 1500.00,
        'amount_paid' => 500.00,
        'balance' => 1000.00,
        'payment_status' => Transaction::PAYMENT_STATUS_PARTIAL,
        'ddn_amount' => 1500.00,
    ]);

    // Initial cash downpayment produces 0 gcash ledger entries
    expect(GcashLedgerEntry::where('transaction_id', $transaction->id)->count())->toBe(0);

    // 2. Client returns on 2026-09-10 to collect item and pays 1,000 remaining balance via GCash
    $response = $this->actingAs($user)->post(route('transactions.payments.store', $transaction->id), [
        'amount' => 1000.00,
        'payment_method' => Transaction::PAYMENT_METHOD_GCASH,
        'payment_date' => '2026-09-10',
        'notes' => 'Remaining balance collected via GCash on pickup',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    // Check that a GCash ledger entry was created on 2026-09-10 for 1,000
    $entries = GcashLedgerEntry::where('transaction_id', $transaction->id)
        ->where('type', GcashLedgerEntry::TYPE_CUSTOMER_PAYMENT)
        ->get();

    expect($entries)->toHaveCount(1);
    $entry = $entries->first();
    expect((float) $entry->amount)->toBe(1000.00);
    expect($entry->entry_date->toDateString())->toBe('2026-09-10');
    expect($entry->notes)->toContain('Payment from Downpayment Client');
    expect($entry->notes)->toContain('Remaining balance collected via GCash on pickup');
});

test('transaction with initial gcash downpayment and subsequent gcash balance collection records both entries', function () {
    $user = User::factory()->create();

    // 1. Transaction with GCash downpayment of 1,000 out of 2,500
    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_PRINTING,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Dual GCash Client',
        'amount' => 2500.00,
        'payment_method' => Transaction::PAYMENT_METHOD_GCASH,
        'payment_status' => Transaction::PAYMENT_STATUS_PARTIAL,
        'amount_paid' => 1000.00,
    ]);

    $response->assertSessionHasNoErrors();
    $transaction = Transaction::where('customer_name', 'Dual GCash Client')->first();
    expect($transaction)->not->toBeNull();

    // First payment entry on 2026-09-08 for 1,000
    $firstEntries = GcashLedgerEntry::where('transaction_id', $transaction->id)
        ->where('type', GcashLedgerEntry::TYPE_CUSTOMER_PAYMENT)
        ->get();
    expect($firstEntries)->toHaveCount(1);
    expect((float) $firstEntries->first()->amount)->toBe(1000.00);
    expect($firstEntries->first()->entry_date->toDateString())->toBe('2026-09-08');

    // 2. Client pays remaining 1,500 balance via GCash on 2026-09-12
    $responsePayment = $this->actingAs($user)->post(route('transactions.payments.store', $transaction->id), [
        'amount' => 1500.00,
        'payment_method' => Transaction::PAYMENT_METHOD_GCASH,
        'payment_date' => '2026-09-12',
        'notes' => 'Pickup balance via GCash',
    ]);

    $responsePayment->assertSessionHasNoErrors();

    // Now both entries must exist on their respective dates
    $allEntries = GcashLedgerEntry::where('transaction_id', $transaction->id)
        ->where('type', GcashLedgerEntry::TYPE_CUSTOMER_PAYMENT)
        ->orderBy('entry_date')
        ->get();

    expect($allEntries)->toHaveCount(2);
    expect((float) $allEntries[0]->amount)->toBe(1000.00);
    expect($allEntries[0]->entry_date->toDateString())->toBe('2026-09-08');
    expect((float) $allEntries[1]->amount)->toBe(1500.00);
    expect($allEntries[1]->entry_date->toDateString())->toBe('2026-09-12');
});
