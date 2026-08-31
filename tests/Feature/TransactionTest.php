<?php

use App\Models\BaggageOption;
use App\Models\OnlineAssistanceType;
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

function transactionData(array $overrides = []): array
{
    return array_merge([
        'type' => Transaction::TYPE_AIRLINE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
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

test('an authenticated user can record an airline transaction', function () {
    $user = User::factory()->create();
    $baggage = BaggageOption::query()->first();

    $response = $this->actingAs($user)->post(route('transactions.store'), transactionData([
        'baggage_option_id' => $baggage->id,
    ]));

    $transaction = Transaction::query()->sole();

    $response->assertRedirect(route('transactions.show', $transaction));
    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'type' => 'airline',
        'customer_name' => 'Person 1',
        'pax' => 1,
        'provider' => 'Cebu Pacific',
        'baggage_option_id' => $baggage->id,
        'amount' => '4500.00',
        'ddn_amount' => '4500.00',
    ]);
});

test('an authenticated user can record a document translation transaction with ddn calculation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'translation',
        'payment_method' => 'cash',
        'transaction_date' => '2026-09-01',
        'customer_name' => 'Translation Client',
        'amount' => '1500.00',
        'third_party_payment' => '500.00',
        'notes' => 'English to Spanish',
    ]);

    $transaction = Transaction::query()->sole();

    $response->assertRedirect(route('transactions.show', $transaction));
    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'type' => 'translation',
        'amount' => '1500.00',
        'third_party_payment' => '500.00',
        'ddn_amount' => '1000.00',
    ]);
});

test('an authenticated user can record a PSA assistance transaction with ddn calculation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'psa',
        'payment_method' => 'gcash',
        'transaction_date' => '2026-09-01',
        'customer_name' => 'PSA Client',
        'psa_document_type' => 'Cenomar',
        'amount' => '1200.00',
        'third_party_payment' => '500.00',
    ]);

    $transaction = Transaction::query()->sole();

    $response->assertRedirect(route('transactions.show', $transaction));
    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'type' => 'psa',
        'payment_method' => 'gcash',
        'psa_document_type' => 'Cenomar',
        'amount' => '1200.00',
        'third_party_payment' => '500.00',
        'ddn_amount' => '700.00',
    ]);
});

test('an authenticated user can record an online assistance transaction with 60/40 revenue split', function () {
    $user = User::factory()->create();
    $type = OnlineAssistanceType::query()->first();

    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'online_assistance',
        'payment_method' => 'gcash',
        'transaction_date' => '2026-09-01',
        'customer_name' => 'Online Client',
        'online_assistance_type_id' => $type->id,
        'amount' => '1000.00',
    ]);

    $transaction = Transaction::query()->sole();

    $response->assertRedirect(route('transactions.show', $transaction));
    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'type' => 'online_assistance',
        'amount' => '1000.00',
        'ddn_amount' => '600.00',
        'employee_share' => '400.00',
    ]);
});

test('an authenticated user can record a printing transaction', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'printing',
        'payment_method' => 'cash',
        'transaction_date' => '2026-09-01',
        'customer_name' => 'Print Client',
        'amount' => '150.00',
    ]);

    $transaction = Transaction::query()->sole();

    $response->assertRedirect(route('transactions.show', $transaction));
    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'type' => 'printing',
        'amount' => '150.00',
        'ddn_amount' => '150.00',
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
