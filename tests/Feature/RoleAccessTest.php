<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('admin can access dashboard, reports, portal deposits, and configurations', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('reports.index'))->assertOk();
    $this->actingAs($admin)->get(route('portal-deposits.index'))->assertOk();
    $this->actingAs($admin)->get(route('configurations.index'))->assertOk();
});

test('staff visiting dashboard is redirected to transactions index', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertRedirect(route('transactions.index'));
});

test('staff is forbidden from accessing reports, portal deposits, and configurations', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('reports.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('portal-deposits.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('configurations.index'))->assertForbidden();
});

function createTransaction(array $attributes = []): Transaction
{
    return Transaction::create(array_merge([
        'type' => Transaction::TYPE_PRINTING,
        'customer_name' => 'Test Customer',
        'amount' => 100.00,
        'ddn_amount' => 100.00,
        'amount_paid' => 100.00,
        'balance' => 0.00,
        'payment_status' => Transaction::PAYMENT_STATUS_PAID,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => now()->toDateString(),
    ], $attributes));
}

test('staff can update and delete their own transaction created today', function () {
    $staff = User::factory()->staff()->create();

    $transaction = createTransaction([
        'user_id' => $staff->id,
        'created_at' => now(),
    ]);

    $this->actingAs($staff)
        ->get(route('transactions.edit', $transaction))
        ->assertOk();

    $this->actingAs($staff)
        ->delete(route('transactions.destroy', $transaction))
        ->assertRedirect(route('transactions.index'));

    $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
});

test('staff cannot update or delete their own transaction from a past day', function () {
    $staff = User::factory()->staff()->create();

    $transaction = createTransaction([
        'user_id' => $staff->id,
    ]);
    $transaction->created_at = now()->subDays(2);
    $transaction->saveQuietly();

    $this->actingAs($staff)
        ->get(route('transactions.edit', $transaction))
        ->assertForbidden();

    $this->actingAs($staff)
        ->delete(route('transactions.destroy', $transaction))
        ->assertForbidden();
});

test('staff cannot update or delete another users transaction', function () {
    $staff1 = User::factory()->staff()->create();
    $staff2 = User::factory()->staff()->create();

    $transaction = createTransaction([
        'user_id' => $staff2->id,
        'created_at' => now(),
    ]);

    $this->actingAs($staff1)
        ->get(route('transactions.edit', $transaction))
        ->assertForbidden();

    $this->actingAs($staff1)
        ->delete(route('transactions.destroy', $transaction))
        ->assertForbidden();
});

test('staff cannot restore or force delete transactions', function () {
    $staff = User::factory()->staff()->create();

    $transaction = createTransaction([
        'user_id' => $staff->id,
        'deleted_at' => now(),
    ]);

    $this->actingAs($staff)
        ->post(route('transactions.restore', $transaction))
        ->assertForbidden();

    $this->actingAs($staff)
        ->delete(route('transactions.forceDelete', $transaction))
        ->assertForbidden();
});

test('admin can update or delete any transaction regardless of date or creator', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $otherUser = User::factory()->staff()->create();

    $transaction = createTransaction([
        'user_id' => $otherUser->id,
        'created_at' => now()->subMonth(),
    ]);

    $this->actingAs($admin)
        ->get(route('transactions.edit', $transaction))
        ->assertOk();

    $this->actingAs($admin)
        ->delete(route('transactions.destroy', $transaction))
        ->assertRedirect(route('transactions.index'));

    $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
});
