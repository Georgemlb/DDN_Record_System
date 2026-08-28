<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function transactionData(array $overrides = []): array
{
    return array_merge([
        'transaction_date' => '2026-08-28',
        'customer_name' => 'Person 1',
        'pax' => 1,
        'provider' => 'Cebu Pacific',
        'service_details' => 'Additional 20kg',
        'amount' => '4500.00',
        'notes' => 'Recorded by staff.',
    ], $overrides);
}

test('guests cannot access transactions', function () {
    $this->get(route('transactions.index'))->assertRedirect(route('login'));
});

test('an authenticated user can view recorded transactions', function () {
    $user = User::factory()->create();
    $latest = Transaction::query()->create(transactionData(['transaction_date' => '2026-08-28']));
    Transaction::query()->create(transactionData(['customer_name' => 'Person 2', 'transaction_date' => '2026-08-20']));

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index')
            ->has('transactions', 2)
            ->where('transactions.0.id', $latest->id));
});

test('an authenticated user can record a transaction', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('transactions.store'), transactionData());

    $transaction = Transaction::query()->sole();

    $response->assertRedirect(route('transactions.show', $transaction));
    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'customer_name' => 'Person 1',
        'pax' => 1,
        'provider' => 'Cebu Pacific',
        'amount' => '4500.00',
    ]);
});

test('a transaction requires its essential fields and a positive peso amount with at most two decimal places', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), transactionData([
            'transaction_date' => '',
            'customer_name' => '',
            'pax' => 0,
            'amount' => '4500.999',
        ]))
        ->assertRedirect(route('transactions.create'))
        ->assertSessionHasErrors(['transaction_date', 'customer_name', 'pax', 'amount']);

    $this->assertDatabaseCount('transactions', 0);
});

test('an authenticated user can view, update, and delete a transaction', function () {
    $user = User::factory()->create();
    $transaction = Transaction::query()->create(transactionData());

    $this->actingAs($user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Show')
            ->where('transaction.id', $transaction->id)
            ->where('transaction.amount', '4500.00'));

    $this->actingAs($user)
        ->put(route('transactions.update', $transaction), transactionData([
            'customer_name' => 'Person Updated',
            'amount' => '5000.50',
            'notes' => null,
        ]))
        ->assertRedirect(route('transactions.show', $transaction));

    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'customer_name' => 'Person Updated',
        'amount' => '5000.50',
        'notes' => null,
    ]);

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $transaction))
        ->assertRedirect(route('transactions.index'));

    $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
});
