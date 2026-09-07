<?php

use App\Models\FlightPortalDeposit;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('guests are redirected to login when accessing portal deposits', function () {
    $this->get(route('portal-deposits.index'))->assertRedirect(route('login'));
    $this->post(route('portal-deposits.top-up'))->assertRedirect(route('login'));
    $this->post(route('portal-deposits.adjustment'))->assertRedirect(route('login'));
});

test('authenticated users can view portal deposits index with metrics', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('portal-deposits.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal-deposits/Index')
            ->has('deposits')
            ->has('metrics')
            ->where('metrics.current_balance', 0)
            ->where('metrics.is_low_balance', true)
        );
});

test('user can record a top-up without expense link', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('portal-deposits.top-up'), [
            'amount' => 50000.00,
            'entry_date' => '2026-09-07',
            'payment_source' => 'bank_transfer',
            'reference_number' => 'DEP-2026-001',
            'notes' => 'Portal load via BDO',
            'record_as_expense' => false,
        ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('flight_portal_deposits', [
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 50000.00,
        'running_balance' => 50000.00,
        'payment_source' => 'bank_transfer',
        'reference_number' => 'DEP-2026-001',
    ]);
    $this->assertDatabaseCount('expenses', 0);
    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(50000.00);
});

test('user can record a top-up with expense link', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('portal-deposits.top-up'), [
            'amount' => 20000.00,
            'entry_date' => '2026-09-07',
            'payment_source' => 'cash',
            'reference_number' => 'CASH-LOAD-01',
            'notes' => 'Cash drawer reload',
            'record_as_expense' => true,
        ]);

    $response->assertSessionHasNoErrors();

    $deposit = FlightPortalDeposit::first();
    expect($deposit)->not->toBeNull();
    expect((float) $deposit->amount)->toEqual(20000.00);
    expect($deposit->expense_id)->not->toBeNull();

    $this->assertDatabaseHas('expenses', [
        'id' => $deposit->expense_id,
        'amount' => 20000.00,
        'payment_method' => 'cash',
    ]);
});

test('airline transaction with third_party_payment automatically creates ticket deduction in portal ledger', function () {
    $user = User::factory()->create();

    // 1. First add a top-up of 30,000
    FlightPortalDeposit::create([
        'user_id' => $user->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 30000.00,
        'running_balance' => 30000.00,
        'entry_date' => '2026-09-07',
        'payment_source' => 'bank_transfer',
    ]);

    // 2. Create airline transaction
    $response = $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => Transaction::TYPE_AIRLINE,
            'payment_method' => Transaction::PAYMENT_METHOD_CASH,
            'transaction_date' => '2026-09-07',
            'customer_name' => 'Maria Santos',
            'pax' => 1,
            'provider' => 'Cebu Pacific',
            'amount' => 8500.00,
            'third_party_payment' => 7200.00,
        ]);

    $response->assertSessionHasNoErrors();

    $transaction = Transaction::where('customer_name', 'Maria Santos')->first();
    expect($transaction)->not->toBeNull();
    expect((float) $transaction->ddn_amount)->toEqual(1300.00); // 8500 - 7200

    $this->assertDatabaseHas('flight_portal_deposits', [
        'transaction_id' => $transaction->id,
        'type' => FlightPortalDeposit::TYPE_TICKET_DEDUCTION,
        'amount' => 7200.00,
    ]);

    // 30,000 - 7,200 = 22,800
    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(22800.00);
});

test('updating an airline transaction updates ticket deduction and recalculates balances', function () {
    $user = User::factory()->create();

    FlightPortalDeposit::create([
        'user_id' => $user->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 50000.00,
        'running_balance' => 50000.00,
        'entry_date' => '2026-09-07',
    ]);

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'type' => Transaction::TYPE_AIRLINE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-07',
        'customer_name' => 'John Doe',
        'pax' => 1,
        'amount' => 10000.00,
        'third_party_payment' => 8000.00,
        'ddn_amount' => 2000.00,
    ]);
    FlightPortalDeposit::syncTransactionTicketDeduction($transaction);

    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(42000.00);

    // Update transaction to 9,000 net portal cost
    $this->actingAs($user)
        ->put(route('transactions.update', $transaction), [
            'type' => Transaction::TYPE_AIRLINE,
            'payment_method' => Transaction::PAYMENT_METHOD_CASH,
            'transaction_date' => '2026-09-07',
            'customer_name' => 'John Doe',
            'pax' => 1,
            'amount' => 11000.00,
            'third_party_payment' => 9000.00,
        ])
        ->assertSessionHasNoErrors();

    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(41000.00);
});

test('deleting and restoring airline transaction updates portal balance', function () {
    $user = User::factory()->create();

    FlightPortalDeposit::create([
        'user_id' => $user->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 50000.00,
        'running_balance' => 50000.00,
        'entry_date' => '2026-09-07',
    ]);

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'type' => Transaction::TYPE_AIRLINE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-07',
        'customer_name' => 'Jane Smith',
        'pax' => 1,
        'amount' => 5000.00,
        'third_party_payment' => 4000.00,
        'ddn_amount' => 1000.00,
    ]);
    FlightPortalDeposit::syncTransactionTicketDeduction($transaction);

    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(46000.00);

    // Delete transaction
    $this->actingAs($user)->delete(route('transactions.destroy', $transaction));
    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(50000.00);

    // Restore transaction
    $this->actingAs($user)->post(route('transactions.restore', $transaction));
    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(46000.00);
});

test('user can record a manual balance adjustment', function () {
    $user = User::factory()->create();

    FlightPortalDeposit::create([
        'user_id' => $user->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 10000.00,
        'running_balance' => 10000.00,
        'entry_date' => '2026-09-07',
    ]);

    // Apply positive adjustment
    $this->actingAs($user)
        ->post(route('portal-deposits.adjustment'), [
            'amount' => 250.00,
            'entry_date' => '2026-09-07',
            'notes' => 'Monthly portal interest / loyalty credit',
        ])
        ->assertSessionHasNoErrors();

    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(10250.00);

    // Apply debit adjustment
    $this->actingAs($user)
        ->post(route('portal-deposits.adjustment'), [
            'amount' => -150.00,
            'entry_date' => '2026-09-07',
            'notes' => 'Portal maintenance fee debit',
        ])
        ->assertSessionHasNoErrors();

    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(10100.00);
});

test('dashboard view includes portal balance metrics', function () {
    $user = User::factory()->create();

    FlightPortalDeposit::create([
        'user_id' => $user->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 15000.00,
        'running_balance' => 15000.00,
        'entry_date' => '2026-09-07',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('portal')
            ->where('portal.balance', 15000)
            ->where('portal.is_low_balance', false)
        );
});
