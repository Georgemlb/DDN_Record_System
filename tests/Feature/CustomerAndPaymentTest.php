<?php

use App\Models\Customer;
use App\Models\DailySettlement;
use App\Models\GcashLedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\OnlineAssistanceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        OnlineAssistanceTypeSeeder::class,
    ]);
});

test('recurring customers are provided to create transaction view for instant autofill', function () {
    $user = User::factory()->create();
    Customer::create(['name' => 'Maria Clara', 'contact_number' => '09123456789']);

    $this->actingAs($user)
        ->get(route('transactions.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Create')
            ->has('recurringCustomers', 1)
            ->where('recurringCustomers.0.name', 'Maria Clara')
        );
});

test('creating transaction auto-creates or links customer with contact number', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Pedro Penduko',
        'customer_contact' => '09333333333',
        'amount' => '300.00',
        'payment_status' => Transaction::PAYMENT_STATUS_PAID,
        'amount_paid' => '300.00',
    ]);

    $response->assertRedirect();

    $customer = Customer::where('name', 'Pedro Penduko')->first();
    expect($customer)->not->toBeNull();
    expect($customer->contact_number)->toBe('09333333333');

    $transaction = Transaction::where('customer_name', 'Pedro Penduko')->first();
    expect($transaction->customer_id)->toBe($customer->id);
    expect((float) $transaction->amount_paid)->toBe(300.00);
    expect((float) $transaction->balance)->toBe(0.00);
    expect($transaction->payment_status)->toBe(Transaction::PAYMENT_STATUS_PAID);
    expect($transaction->payments)->toHaveCount(1);
});

test('recording partial downpayment sets balance and allows balance collection on pickup', function () {
    $user = User::factory()->create();

    // 1. Create transaction with 2,000 downpayment out of 5,000 total
    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_AIRLINE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Ana Reyes',
        'customer_contact' => '09444444444',
        'pax' => 1,
        'provider' => 'Cebu Pacific',
        'amount' => '5000.00',
        'payment_status' => Transaction::PAYMENT_STATUS_PARTIAL,
        'amount_paid' => '2000.00',
    ]);

    $response->assertRedirect();

    $transaction = Transaction::where('customer_name', 'Ana Reyes')->first();
    expect($transaction)->not->toBeNull();
    expect((float) $transaction->amount)->toBe(5000.00);
    expect((float) $transaction->amount_paid)->toBe(2000.00);
    expect((float) $transaction->balance)->toBe(3000.00);
    expect($transaction->payment_status)->toBe(Transaction::PAYMENT_STATUS_PARTIAL);
    expect($transaction->payments)->toHaveCount(1);
    expect((float) $transaction->payments->first()->amount)->toBe(2000.00);

    // 2. Daily settlement on 2026-09-08 should record 2,000 cash in
    $metricsDay1 = DailySettlement::calculateMetricsForDate('2026-09-08');
    expect($metricsDay1['cash_in'])->toBe(2000.00);

    // 3. Collect the remaining 3,000 balance two days later (2026-09-10)
    $paymentResponse = $this->actingAs($user)->post(route('transactions.payments.store', $transaction->id), [
        'amount' => '3000.00',
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'payment_date' => '2026-09-10',
        'notes' => 'Paid balance in cash on ticket pickup',
    ]);

    $paymentResponse->assertRedirect();

    $transaction->refresh();
    expect((float) $transaction->amount_paid)->toBe(5000.00);
    expect((float) $transaction->balance)->toBe(0.00);
    expect($transaction->payment_status)->toBe(Transaction::PAYMENT_STATUS_PAID);
    expect($transaction->payments)->toHaveCount(2);

    // 4. Daily settlement on 2026-09-10 should record 3,000 cash in from collected balance
    $metricsDay2 = DailySettlement::calculateMetricsForDate('2026-09-10');
    expect($metricsDay2['cash_in'])->toBe(3000.00);
});

test('customer model aggregates total spent and outstanding balance', function () {
    $user = User::factory()->create();
    $customer = Customer::create(['name' => 'Roberto Carlos']);

    Transaction::create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'amount' => 1000.00,
        'amount_paid' => 400.00,
        'balance' => 600.00,
        'payment_status' => Transaction::PAYMENT_STATUS_PARTIAL,
    ]);

    $customerWithAggregates = Customer::withAggregates()->find($customer->id);

    expect((int) $customerWithAggregates->transactions_count)->toBe(1);
    expect((float) $customerWithAggregates->total_spent)->toBe(400.00);
    expect((float) $customerWithAggregates->outstanding_balance)->toBe(600.00);
});

test('transaction can be created with gcash overpayment and cash change', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_GCASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Overpaying Client',
        'amount' => '350.00',
        'payment_status' => Transaction::PAYMENT_STATUS_PAID,
        'amount_paid' => '350.00',
        'gcash_tendered' => '1000.00',
        'cash_change' => '650.00',
        'change_fee' => '0.00',
    ]);

    $response->assertRedirect();

    $transaction = Transaction::where('customer_name', 'Overpaying Client')->first();
    expect($transaction)->not->toBeNull();
    expect((float) $transaction->amount)->toBe(350.00);
    expect((float) $transaction->gcash_tendered)->toBe(1000.00);
    expect((float) $transaction->cash_change)->toBe(650.00);
    expect((float) $transaction->change_fee)->toBe(0.00);

    // Initial payment inherits overpayment attributes
    $payment = $transaction->payments->first();
    expect($payment)->not->toBeNull();
    expect((float) $payment->gcash_tendered)->toBe(1000.00);
    expect((float) $payment->cash_change)->toBe(650.00);
    expect((float) $payment->change_fee)->toBe(0.00);

    // GCash ledger entry has 1000 inflow
    $ledger = GcashLedgerEntry::where('transaction_id', $transaction->id)->first();
    expect($ledger)->not->toBeNull();
    expect((float) $ledger->amount)->toBe(1000.00);
});

test('collecting balance supports gcash overpayment with cash change and fee', function () {
    $user = User::factory()->create();

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Partial Client',
        'amount' => 1500.00,
        'ddn_amount' => 1500.00,
        'amount_paid' => 500.00,
        'balance' => 1000.00,
        'payment_status' => Transaction::PAYMENT_STATUS_PARTIAL,
    ]);

    // Collect the remaining 1,000 balance via GCash, but client sends 1,500, takes 480 cash change, 20 fee
    $response = $this->actingAs($user)->post(route('transactions.payments.store', $transaction->id), [
        'amount' => '1000.00',
        'payment_method' => Transaction::PAYMENT_METHOD_GCASH,
        'payment_date' => '2026-09-09',
        'gcash_tendered' => '1500.00',
        'cash_change' => '480.00',
        'change_fee' => '20.00',
        'notes' => 'Balance collected via GCash with cash change',
    ]);

    $response->assertRedirect();

    $transaction->refresh();
    expect((float) $transaction->balance)->toBe(0.00);
    expect((float) $transaction->amount_paid)->toBe(1500.00);
    expect((float) $transaction->ddn_amount)->toBe(1500.00);

    $payment = $transaction->payments()->latest('id')->first();
    expect($payment)->not->toBeNull();
    expect((float) $payment->amount)->toBe(1000.00);
    expect((float) $payment->gcash_tendered)->toBe(1500.00);
    expect((float) $payment->cash_change)->toBe(480.00);
    expect((float) $payment->change_fee)->toBe(20.00);
});

test('updating single-payment transaction synchronizes initial payment and keeps balance accurate', function () {
    $user = User::factory()->create();

    // 1. Create a paid transaction for 2,000 Cash
    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Initial Client',
        'amount' => '2000.00',
        'payment_status' => Transaction::PAYMENT_STATUS_PAID,
        'amount_paid' => '2000.00',
    ]);

    $response->assertSessionHasNoErrors();
    $transaction = Transaction::where('customer_name', 'Initial Client')->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->payments)->toHaveCount(1);
    expect((float) $transaction->payments->first()->amount)->toBe(2000.00);
    expect($transaction->payments->first()->payment_method)->toBe(Transaction::PAYMENT_METHOD_CASH);

    // 2. User edits the transaction: updates price to 2,500 and switches payment method to GCash
    $updateResponse = $this->actingAs($user)->put(route('transactions.update', $transaction), [
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_GCASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Initial Client',
        'amount' => '2500.00',
        'payment_status' => Transaction::PAYMENT_STATUS_PAID,
        'amount_paid' => '2500.00',
    ]);

    $updateResponse->assertSessionHasNoErrors();
    $transaction->refresh();

    // Transaction balance remains 0 and status remains paid
    expect((float) $transaction->amount)->toBe(2500.00);
    expect((float) $transaction->amount_paid)->toBe(2500.00);
    expect((float) $transaction->balance)->toBe(0.00);
    expect($transaction->payment_status)->toBe(Transaction::PAYMENT_STATUS_PAID);

    // Initial TransactionPayment was updated to 2,500 GCash
    $payments = $transaction->payments()->get();
    expect($payments)->toHaveCount(1);
    expect((float) $payments->first()->amount)->toBe(2500.00);
    expect($payments->first()->payment_method)->toBe(Transaction::PAYMENT_METHOD_GCASH);

    // Daily settlement for date accurately reflects GCash rather than Cash
    $metrics = DailySettlement::calculateMetricsForDate('2026-09-08');
    expect((float) $metrics['cash_in'])->toBe(0.00);
    expect((float) $metrics['total_gcash'])->toBe(2500.00);

    // GCash ledger entry has 2,500 inflow
    $ledger = GcashLedgerEntry::where('transaction_id', $transaction->id)->first();
    expect($ledger)->not->toBeNull();
    expect((float) $ledger->amount)->toBe(2500.00);
});

test('soft-deleting and restoring transaction removes and restores its payments from daily settlements and dashboard', function () {
    $user = User::factory()->create();

    // 1. Create a paid transaction for 3,000 Cash
    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Delete Restore Client',
        'amount' => '3000.00',
        'payment_status' => Transaction::PAYMENT_STATUS_PAID,
        'amount_paid' => '3000.00',
    ]);

    $response->assertSessionHasNoErrors();
    $transaction = Transaction::where('customer_name', 'Delete Restore Client')->first();
    expect($transaction)->not->toBeNull();

    // Pre-delete: metrics show 3,000 Cash
    $metricsActive = DailySettlement::calculateMetricsForDate('2026-09-08');
    expect((float) $metricsActive['cash_in'])->toBe(3000.00);

    // 2. Move transaction to trash (soft-delete)
    $deleteResponse = $this->actingAs($user)->delete(route('transactions.destroy', $transaction));
    $deleteResponse->assertRedirect();
    expect($transaction->fresh()->trashed())->toBeTrue();

    // Post-delete: metrics must exclude the soft-deleted transaction's payments
    $metricsDeleted = DailySettlement::calculateMetricsForDate('2026-09-08');
    expect((float) $metricsDeleted['cash_in'])->toBe(0.00);
    expect((float) $metricsDeleted['total_sales'])->toBe(0.00);

    // 3. Restore the transaction
    $restoreResponse = $this->actingAs($user)->post(route('transactions.restore', $transaction));
    $restoreResponse->assertRedirect();
    expect($transaction->fresh()->trashed())->toBeFalse();

    // Post-restore: metrics count the transaction payments again
    $metricsRestored = DailySettlement::calculateMetricsForDate('2026-09-08');
    expect((float) $metricsRestored['cash_in'])->toBe(3000.00);
    expect((float) $metricsRestored['total_sales'])->toBe(3000.00);
});

test('staff and admin are authorized to collect payments on transactions', function () {
    $admin = User::factory()->create();
    $staff = User::factory()->staff()->create();

    $transaction = Transaction::create([
        'user_id' => $admin->id,
        'customer_name' => 'Policy Test Client',
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'amount' => 1000.00,
        'amount_paid' => 400.00,
        'balance' => 600.00,
        'payment_status' => Transaction::PAYMENT_STATUS_PARTIAL,
    ]);

    // Guest cannot collect payment
    $this->post(route('transactions.payments.store', $transaction->id), [
        'amount' => 300.00,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'payment_date' => '2026-09-09',
    ])->assertRedirect(route('login'));

    // Staff can collect payment
    $this->actingAs($staff)->post(route('transactions.payments.store', $transaction->id), [
        'amount' => 300.00,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'payment_date' => '2026-09-09',
    ])->assertRedirect();

    expect((float) $transaction->fresh()->balance)->toBe(300.00);
});

test('transaction edit view loads payments relation for multi-payment locking', function () {
    $user = User::factory()->create();

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'customer_name' => 'Multi Payment Edit Client',
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'amount' => 1000.00,
        'amount_paid' => 400.00,
        'balance' => 600.00,
        'payment_status' => Transaction::PAYMENT_STATUS_PARTIAL,
    ]);

    $transaction->payments()->create([
        'user_id' => $user->id,
        'amount' => 300.00,
        'payment_method' => Transaction::PAYMENT_METHOD_GCASH,
        'payment_date' => '2026-09-09',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.edit', $transaction))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('transactions/Edit')
            ->has('transaction.payments', 2)
        );
});
