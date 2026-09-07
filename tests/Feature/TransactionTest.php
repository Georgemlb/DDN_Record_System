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
            ->has('transactions.data', 2)
            ->where('transactions.data.0.id', $latest->id));
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

    $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
});

test('transaction automatically generates date-based sequential reference number', function () {
    $user = User::factory()->create();

    $tx1 = Transaction::query()->create(transactionData([
        'transaction_date' => '2026-09-07',
        'customer_name' => 'Client A',
    ]));

    $tx2 = Transaction::query()->create(transactionData([
        'transaction_date' => '2026-09-07',
        'customer_name' => 'Client B',
    ]));

    expect($tx1->reference_number)->toBe('TRX-20260907-0001')
        ->and($tx2->reference_number)->toBe('TRX-20260907-0002');
});

test('transaction automatically associates authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('transactions.store'), transactionData());

    $transaction = Transaction::query()->latest('id')->first();
    expect($transaction->user_id)->toBe($user->id);
});

test('soft-deleted transactions can be viewed in trash and restored', function () {
    $user = User::factory()->create();
    $tx = Transaction::query()->create(transactionData());

    $tx->delete();

    // Not visible in active index
    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index')
            ->has('transactions.data', 0)
            ->where('counts.trash', 1)
        );

    // Visible in trash index
    $this->actingAs($user)
        ->get(route('transactions.index', ['status' => 'trash']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.id', $tx->id)
        );

    // Restore transaction
    $this->actingAs($user)
        ->post(route('transactions.restore', $tx))
        ->assertRedirect();

    expect($tx->fresh()->trashed())->toBeFalse();
});

test('transactions can be searched by reference number', function () {
    $user = User::factory()->create();
    $tx = Transaction::query()->create(transactionData([
        'customer_name' => 'Unique Search Person',
    ]));

    $this->actingAs($user)
        ->get(route('transactions.index', ['search' => $tx->reference_number]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.id', $tx->id)
        );
});

test('transactions can be filtered by custom date range', function () {
    $user = User::factory()->create();

    Transaction::query()->create(transactionData([
        'customer_name' => 'InRange Customer',
        'transaction_date' => '2026-08-15',
        'amount' => '1500.00',
    ]));

    Transaction::query()->create(transactionData([
        'customer_name' => 'OutOfRange Customer',
        'transaction_date' => '2026-08-01',
        'amount' => '2500.00',
    ]));

    $this->actingAs($user)
        ->get(route('transactions.index', [
            'date' => 'custom',
            'date_from' => '2026-08-10',
            'date_to' => '2026-08-20',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.customer_name', 'InRange Customer')
            ->where('summary.total_payments', 1500)
        );
});
