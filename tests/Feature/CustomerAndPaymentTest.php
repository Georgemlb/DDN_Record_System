<?php

use App\Models\Customer;
use App\Models\DailySettlement;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\BaggageOptionSeeder;
use Database\Seeders\OnlineAssistanceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        BaggageOptionSeeder::class,
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
