<?php

use App\Models\PsaRate;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PsaRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PsaRateSeeder::class);
});

test('transaction create and edit pages share configured active psa rates', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('transactions.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Create')
            ->has('psaRates', 4)
            ->where('psaRates.0.document_type', 'BC')
            ->where('psaRates.0.price', '320.00')
            ->where('psaRates.0.officer_fee', '260.00')
        );

    $transaction = Transaction::create([
        'type' => Transaction::TYPE_PSA,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Jane Doe',
        'psa_document_type' => 'BC',
        'amount' => 320.00,
        'third_party_payment' => 260.00,
        'ddn_amount' => 60.00,
    ]);

    $this->actingAs($staff)
        ->get(route('transactions.edit', $transaction))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Edit')
            ->has('psaRates', 4)
        );
});

test('creating a psa transaction automatically applies configured price, officer fee, and calculates net ddn income', function () {
    $staff = User::factory()->staff()->create();

    // Staff submits without third_party_payment (field is hidden in UI)
    $response = $this->actingAs($staff)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_PSA,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Maria Clara',
        'psa_document_type' => 'BC',
        'notes' => 'PSA Birth Certificate request',
    ]);

    $transaction = Transaction::query()->where('customer_name', 'Maria Clara')->firstOrFail();

    $response->assertRedirect(route('transactions.show', $transaction));
    expect((float) $transaction->amount)->toEqual(320.00)
        ->and((float) $transaction->third_party_payment)->toEqual(260.00)
        ->and((float) $transaction->ddn_amount)->toEqual(60.00)
        ->and((float) $transaction->balance)->toEqual(0.00)
        ->and($transaction->payment_status)->toEqual(Transaction::PAYMENT_STATUS_PAID);
});

test('creating a cenomar transaction automatically applies cenomar configured rates', function () {
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($staff)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_PSA,
        'payment_method' => Transaction::PAYMENT_METHOD_GCASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Crisostomo Ibarra',
        'psa_document_type' => 'CENOMAR',
    ]);

    $transaction = Transaction::query()->where('customer_name', 'Crisostomo Ibarra')->firstOrFail();

    $response->assertRedirect(route('transactions.show', $transaction));
    expect((float) $transaction->amount)->toEqual(400.00)
        ->and((float) $transaction->third_party_payment)->toEqual(310.00)
        ->and((float) $transaction->ddn_amount)->toEqual(90.00);
});

test('admin updating psa rate dynamically updates newly recorded transactions', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $staff = User::factory()->staff()->create();

    $bcRate = PsaRate::where('document_type', 'BC')->firstOrFail();

    // Admin updates BC rate to 350 customer price and 270 officer fee
    $this->actingAs($admin)->put("/configurations/psa-rates/{$bcRate->id}", [
        'document_type' => 'BC',
        'name' => 'Birth Certificate (Updated)',
        'price' => 350.00,
        'officer_fee' => 270.00,
        'is_active' => true,
        'sort_order' => 1,
    ])->assertRedirect('/configurations?tab=psa-rates');

    $bcRate->refresh();
    expect((float) $bcRate->price)->toEqual(350.00)
        ->and((float) $bcRate->officer_fee)->toEqual(270.00)
        ->and((float) $bcRate->net_income)->toEqual(80.00);

    // Staff creates new BC transaction under updated rate
    $this->actingAs($staff)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_PSA,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Elias Salome',
        'psa_document_type' => 'BC',
    ]);

    $transaction = Transaction::query()->where('customer_name', 'Elias Salome')->firstOrFail();

    expect((float) $transaction->amount)->toEqual(350.00)
        ->and((float) $transaction->third_party_payment)->toEqual(270.00)
        ->and((float) $transaction->ddn_amount)->toEqual(80.00);
});

test('creating a psa transaction with multiple copies automatically multiplies price, officer fee, and net income', function () {
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($staff)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_PSA,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Donya Victorina',
        'psa_document_type' => 'BC',
        'copies' => 2,
    ]);

    $transaction = Transaction::query()->where('customer_name', 'Donya Victorina')->firstOrFail();

    $response->assertRedirect(route('transactions.show', $transaction));
    expect($transaction->copies)->toEqual(2)
        ->and((float) $transaction->amount)->toEqual(640.00) // 2 * 320
        ->and((float) $transaction->third_party_payment)->toEqual(520.00) // 2 * 260
        ->and((float) $transaction->ddn_amount)->toEqual(120.00) // 2 * 60
        ->and((float) $transaction->balance)->toEqual(0.00)
        ->and($transaction->payment_status)->toEqual(Transaction::PAYMENT_STATUS_PAID);
});

test('updating an existing psa transaction copies updates amount and third party payment accordingly', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->post(route('transactions.store'), [
        'type' => Transaction::TYPE_PSA,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Paulita Gomez',
        'psa_document_type' => 'CENOMAR',
        'copies' => 1,
    ]);

    $transaction = Transaction::query()->where('customer_name', 'Paulita Gomez')->firstOrFail();
    expect($transaction->copies)->toEqual(1)
        ->and((float) $transaction->amount)->toEqual(400.00);

    $updateResponse = $this->actingAs($staff)->put(route('transactions.update', $transaction), [
        'type' => Transaction::TYPE_PSA,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-08',
        'customer_name' => 'Paulita Gomez',
        'psa_document_type' => 'CENOMAR',
        'copies' => 3,
    ]);

    $updateResponse->assertRedirect(route('transactions.show', $transaction));
    $transaction->refresh();

    expect($transaction->copies)->toEqual(3)
        ->and((float) $transaction->amount)->toEqual(1200.00) // 3 * 400
        ->and((float) $transaction->third_party_payment)->toEqual(930.00) // 3 * 310
        ->and((float) $transaction->ddn_amount)->toEqual(270.00); // 3 * 90
});
