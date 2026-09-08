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

test('admin can update a top-up deposit entry', function () {
    $admin = User::factory()->create();

    $deposit = FlightPortalDeposit::create([
        'user_id' => $admin->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 50000.00,
        'running_balance' => 50000.00,
        'entry_date' => '2026-09-07',
        'payment_source' => 'cash',
        'reference_number' => 'DEP-OLD',
        'notes' => 'Old note',
    ]);

    $response = $this->actingAs($admin)
        ->put(route('portal-deposits.update', $deposit), [
            'amount' => 60000.00,
            'entry_date' => '2026-09-08',
            'payment_source' => 'bank_transfer',
            'reference_number' => 'DEP-NEW',
            'notes' => 'Updated reload notes',
        ]);

    $response->assertSessionHasNoErrors();
    $deposit->refresh();

    expect((float) $deposit->amount)->toEqual(60000.00);
    expect($deposit->entry_date->toDateString())->toEqual('2026-09-08');
    expect($deposit->payment_source)->toEqual('bank_transfer');
    expect($deposit->reference_number)->toEqual('DEP-NEW');
    expect($deposit->notes)->toEqual('Updated reload notes');
    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(60000.00);
});

test('updating top-up with linked expense synchronizes expense and daily settlements', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('portal-deposits.top-up'), [
            'amount' => 20000.00,
            'entry_date' => '2026-09-07',
            'payment_source' => 'cash',
            'reference_number' => 'CASH-001',
            'notes' => 'Cash topup',
            'record_as_expense' => true,
        ]);

    $deposit = FlightPortalDeposit::first();
    expect($deposit->expense_id)->not->toBeNull();

    $this->actingAs($admin)
        ->put(route('portal-deposits.update', $deposit), [
            'amount' => 25000.00,
            'entry_date' => '2026-09-08',
            'payment_source' => 'bank_transfer',
            'reference_number' => 'BANK-002',
            'notes' => 'Changed to bank',
        ])
        ->assertSessionHasNoErrors();

    $deposit->refresh();
    $expense = $deposit->expense;

    expect((float) $deposit->amount)->toEqual(25000.00);
    expect((float) $expense->amount)->toEqual(25000.00);
    expect($expense->payment_method)->toEqual('bank_transfer');
    expect($expense->expense_date->toDateString())->toEqual('2026-09-08');
    expect($expense->reference_number)->toEqual('BANK-002');
    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(25000.00);
});

test('admin can update a manual balance adjustment', function () {
    $admin = User::factory()->create();

    FlightPortalDeposit::create([
        'user_id' => $admin->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 10000.00,
        'running_balance' => 10000.00,
        'entry_date' => '2026-09-07',
    ]);

    $adj = FlightPortalDeposit::create([
        'user_id' => $admin->id,
        'type' => FlightPortalDeposit::TYPE_ADJUSTMENT,
        'amount' => 500.00,
        'running_balance' => 10500.00,
        'entry_date' => '2026-09-07',
        'notes' => 'Credit adjustment',
    ]);

    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(10500.00);

    // Update adjustment to -300 debit
    $this->actingAs($admin)
        ->put(route('portal-deposits.update', $adj), [
            'amount' => -300.00,
            'entry_date' => '2026-09-07',
            'notes' => 'Fee debit',
        ])
        ->assertSessionHasNoErrors();

    $adj->refresh();
    expect((float) $adj->amount)->toEqual(-300.00);
    expect($adj->notes)->toEqual('Fee debit');
    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(9700.00);
});

test('updating deposit validates inputs', function () {
    $admin = User::factory()->create();

    $deposit = FlightPortalDeposit::create([
        'user_id' => $admin->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 10000.00,
        'running_balance' => 10000.00,
        'entry_date' => '2026-09-07',
        'payment_source' => 'cash',
    ]);

    $this->actingAs($admin)
        ->put(route('portal-deposits.update', $deposit), [
            'amount' => -500.00,
            'entry_date' => 'not-a-date',
            'payment_source' => 'invalid_source',
        ])
        ->assertSessionHasErrors(['amount', 'entry_date', 'payment_source']);
});

test('updating transaction linked ticket deduction directly is rejected', function () {
    $admin = User::factory()->create();

    $transaction = Transaction::create([
        'user_id' => $admin->id,
        'type' => Transaction::TYPE_AIRLINE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-07',
        'customer_name' => 'John Doe',
        'pax' => 1,
        'amount' => 5000.00,
        'third_party_payment' => 4000.00,
    ]);
    $deduction = FlightPortalDeposit::syncTransactionTicketDeduction($transaction);

    $this->actingAs($admin)
        ->put(route('portal-deposits.update', $deduction), [
            'amount' => 3000.00,
            'entry_date' => '2026-09-07',
        ]);

    $deduction->refresh();
    expect((float) $deduction->amount)->toEqual(4000.00);
});

test('staff cannot update portal deposits', function () {
    $staff = User::factory()->staff()->create();

    $deposit = FlightPortalDeposit::create([
        'user_id' => $staff->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 10000.00,
        'running_balance' => 10000.00,
        'entry_date' => '2026-09-07',
        'payment_source' => 'cash',
    ]);

    $this->actingAs($staff)
        ->put(route('portal-deposits.update', $deposit), [
            'amount' => 20000.00,
            'entry_date' => '2026-09-07',
            'payment_source' => 'cash',
        ])
        ->assertForbidden();
});

test('admin can set a running balance anchor override on a row', function () {
    $admin = User::factory()->create();

    $row1 = FlightPortalDeposit::create([
        'user_id' => $admin->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 10000.00,
        'running_balance' => 10000.00,
        'entry_date' => '2026-09-01',
        'payment_source' => 'cash',
    ]);

    $row2 = FlightPortalDeposit::create([
        'user_id' => $admin->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 5000.00,
        'running_balance' => 15000.00,
        'entry_date' => '2026-09-02',
        'payment_source' => 'cash',
    ]);

    $row3 = FlightPortalDeposit::create([
        'user_id' => $admin->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 2000.00,
        'running_balance' => 17000.00,
        'entry_date' => '2026-09-03',
        'payment_source' => 'cash',
    ]);

    // Admin sets Row 2 as an anchor with 50,000.00
    $this->actingAs($admin)
        ->put(route('portal-deposits.update', $row2), [
            'amount' => 5000.00,
            'entry_date' => '2026-09-02',
            'payment_source' => 'cash',
            'is_balance_anchor' => true,
            'running_balance' => 50000.00,
        ])
        ->assertSessionHasNoErrors();

    $row1->refresh();
    $row2->refresh();
    $row3->refresh();

    expect($row1->running_balance)->toEqual('10000.00');
    expect($row2->is_balance_anchor)->toBeTrue();
    expect($row2->running_balance)->toEqual('50000.00');
    // Row 3 must calculate forward from the 50,000.00 anchor: 50,000 + 2,000 = 52,000.00
    expect($row3->running_balance)->toEqual('52000.00');
    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(52000.00);
});

test('admin can reset a running balance anchor back to auto-calculation', function () {
    $admin = User::factory()->create();

    $row1 = FlightPortalDeposit::create([
        'user_id' => $admin->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 10000.00,
        'running_balance' => 10000.00,
        'entry_date' => '2026-09-01',
        'payment_source' => 'cash',
    ]);

    $row2 = FlightPortalDeposit::create([
        'user_id' => $admin->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 5000.00,
        'running_balance' => 50000.00,
        'is_balance_anchor' => true,
        'entry_date' => '2026-09-02',
        'payment_source' => 'cash',
    ]);

    $row3 = FlightPortalDeposit::create([
        'user_id' => $admin->id,
        'type' => FlightPortalDeposit::TYPE_TOP_UP,
        'amount' => 2000.00,
        'running_balance' => 52000.00,
        'entry_date' => '2026-09-03',
        'payment_source' => 'cash',
    ]);

    // Reset Row 2 back to auto
    $this->actingAs($admin)
        ->put(route('portal-deposits.update', $row2), [
            'amount' => 5000.00,
            'entry_date' => '2026-09-02',
            'payment_source' => 'cash',
            'is_balance_anchor' => false,
        ])
        ->assertSessionHasNoErrors();

    $row1->refresh();
    $row2->refresh();
    $row3->refresh();

    expect($row2->is_balance_anchor)->toBeFalse();
    expect($row2->running_balance)->toEqual('15000.00');
    expect($row3->running_balance)->toEqual('17000.00');
    expect(FlightPortalDeposit::getCurrentBalance())->toEqual(17000.00);
});
