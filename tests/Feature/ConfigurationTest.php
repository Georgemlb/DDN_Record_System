<?php

use App\Models\BaggageOption;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\OnlineAssistanceType;
use App\Models\PsaRate;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\BaggageOptionSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\OnlineAssistanceTypeSeeder;
use Database\Seeders\PsaRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        BaggageOptionSeeder::class,
        OnlineAssistanceTypeSeeder::class,
        ExpenseCategorySeeder::class,
        PsaRateSeeder::class,
    ]);
});

test('guests cannot access configurations', function () {
    $this->get(route('configurations.index'))->assertRedirect(route('login'));
});

test('an authenticated user can view configurations page with all master data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('configurations.index', ['tab' => 'online-assistance']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('configurations/Index')
            ->has('baggageOptions')
            ->has('onlineAssistanceTypes')
            ->has('expenseCategories')
            ->has('psaRates')
            ->where('currentTab', 'online-assistance')
        );
});

test('an authenticated user can create, update, and delete a baggage option', function () {
    $user = User::factory()->create();

    // Create
    $response = $this->actingAs($user)->post('/configurations/baggage-options', [
        'airline' => 'Philippine Airlines',
        'label' => '+25 KG',
        'weight_kg' => 25,
        'is_active' => true,
        'sort_order' => 10,
    ]);

    $response->assertRedirect('/configurations?tab=baggage');
    $this->assertDatabaseHas('baggage_options', [
        'airline' => 'Philippine Airlines',
        'label' => '+25 KG',
        'weight_kg' => 25,
    ]);

    $option = BaggageOption::where('label', '+25 KG')->first();

    // Update
    $updateResponse = $this->actingAs($user)->put("/configurations/baggage-options/{$option->id}", [
        'airline' => 'Philippine Airlines',
        'label' => '+25 KG Extra',
        'weight_kg' => 25,
        'is_active' => false,
        'sort_order' => 11,
    ]);

    $updateResponse->assertRedirect('/configurations?tab=baggage');
    $this->assertDatabaseHas('baggage_options', [
        'id' => $option->id,
        'label' => '+25 KG Extra',
        'is_active' => false,
    ]);

    // Delete (unused)
    $deleteResponse = $this->actingAs($user)->delete("/configurations/baggage-options/{$option->id}");
    $deleteResponse->assertRedirect('/configurations?tab=baggage');
    $this->assertDatabaseMissing('baggage_options', ['id' => $option->id]);
});

test('an authenticated user cannot delete a baggage option if used in transactions', function () {
    $user = User::factory()->create();
    $option = BaggageOption::first();

    Transaction::create([
        'type' => Transaction::TYPE_AIRLINE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-08-28',
        'customer_name' => 'Person 1',
        'pax' => 1,
        'amount' => '4500.00',
        'baggage_option_id' => $option->id,
    ]);

    $response = $this->actingAs($user)->delete("/configurations/baggage-options/{$option->id}");
    $response->assertRedirect('/configurations?tab=baggage');
    $this->assertDatabaseHas('baggage_options', ['id' => $option->id]);
});

test('an authenticated user can create, update, and delete an online assistance type', function () {
    $user = User::factory()->create();

    // Create
    $response = $this->actingAs($user)->post('/configurations/online-assistance-types', [
        'name' => 'Passport Appointment',
        'price' => '500.00',
        'is_active' => true,
        'sort_order' => 5,
    ]);

    $response->assertRedirect('/configurations?tab=online-assistance');
    $this->assertDatabaseHas('online_assistance_types', [
        'name' => 'Passport Appointment',
    ]);

    $type = OnlineAssistanceType::where('name', 'Passport Appointment')->first();

    // Update
    $updateResponse = $this->actingAs($user)->put("/configurations/online-assistance-types/{$type->id}", [
        'name' => 'Passport Expedited Appointment',
        'price' => '750.00',
        'is_active' => true,
        'sort_order' => 5,
    ]);

    $updateResponse->assertRedirect('/configurations?tab=online-assistance');
    $this->assertDatabaseHas('online_assistance_types', [
        'id' => $type->id,
        'name' => 'Passport Expedited Appointment',
    ]);

    // Delete
    $deleteResponse = $this->actingAs($user)->delete("/configurations/online-assistance-types/{$type->id}");
    $deleteResponse->assertRedirect('/configurations?tab=online-assistance');
    $this->assertDatabaseMissing('online_assistance_types', ['id' => $type->id]);
});

test('an authenticated user cannot delete an online assistance type if used in transactions', function () {
    $user = User::factory()->create();
    $type = OnlineAssistanceType::first();

    Transaction::create([
        'type' => Transaction::TYPE_ONLINE_ASSISTANCE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-08-28',
        'customer_name' => 'Person 1',
        'amount' => '300.00',
        'online_assistance_type_id' => $type->id,
    ]);

    $response = $this->actingAs($user)->delete("/configurations/online-assistance-types/{$type->id}");
    $response->assertRedirect('/configurations?tab=online-assistance');
    $this->assertDatabaseHas('online_assistance_types', ['id' => $type->id]);
});

test('an authenticated user can create, update, and delete an expense category', function () {
    $user = User::factory()->create();

    // Create
    $response = $this->actingAs($user)->post('/configurations/expense-categories', [
        'name' => 'Office Cleaning Supplies',
        'description' => 'Disinfectants and mops',
        'is_active' => true,
        'sort_order' => 8,
    ]);

    $response->assertRedirect('/configurations?tab=expense-categories');
    $this->assertDatabaseHas('expense_categories', [
        'name' => 'Office Cleaning Supplies',
    ]);

    $category = ExpenseCategory::where('name', 'Office Cleaning Supplies')->first();

    // Update
    $updateResponse = $this->actingAs($user)->put("/configurations/expense-categories/{$category->id}", [
        'name' => 'Janitorial & Cleaning',
        'description' => 'All hygiene and cleaning items',
        'is_active' => false,
        'sort_order' => 9,
    ]);

    $updateResponse->assertRedirect('/configurations?tab=expense-categories');
    $this->assertDatabaseHas('expense_categories', [
        'id' => $category->id,
        'name' => 'Janitorial & Cleaning',
        'is_active' => false,
    ]);

    // Delete
    $deleteResponse = $this->actingAs($user)->delete("/configurations/expense-categories/{$category->id}");
    $deleteResponse->assertRedirect('/configurations?tab=expense-categories');
    $this->assertDatabaseMissing('expense_categories', ['id' => $category->id]);
});

test('an authenticated user cannot delete an expense category if used in expenses', function () {
    $user = User::factory()->create();
    $category = ExpenseCategory::first();

    Expense::create([
        'title' => 'Internet Bill',
        'expense_category_id' => $category->id,
        'amount' => '1500.00',
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'expense_date' => '2026-08-28',
    ]);

    $response = $this->actingAs($user)->delete("/configurations/expense-categories/{$category->id}");
    $response->assertRedirect('/configurations?tab=expense-categories');
    $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);
});

test('legacy settings configuration routes redirect to configurations with tab query', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/baggage-options')
        ->assertRedirect('/configurations?tab=baggage');

    $this->actingAs($user)
        ->get('/settings/online-assistance-types')
        ->assertRedirect('/configurations?tab=online-assistance');

    $this->actingAs($user)
        ->get('/settings/expense-categories')
        ->assertRedirect('/configurations?tab=expense-categories');
});

test('an authenticated user cannot create online assistance type with duplicate name', function () {
    $user = User::factory()->create();
    $existing = OnlineAssistanceType::first();

    $response = $this->actingAs($user)->post('/configurations/online-assistance-types', [
        'name' => $existing->name,
        'price' => '600.00',
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('submitting null or empty sort_order defaults safely to 0 across configuration entities', function () {
    $user = User::factory()->create();

    // Baggage Option with null sort_order
    $this->actingAs($user)->post('/configurations/baggage-options', [
        'airline' => 'Test Air',
        'label' => '+15 KG Test',
        'weight_kg' => 15,
        'price' => '800.00',
        'sort_order' => null,
    ])->assertRedirect('/configurations?tab=baggage');

    $this->assertDatabaseHas('baggage_options', [
        'label' => '+15 KG Test',
        'sort_order' => 0,
    ]);

    // Online Assistance Type with null sort_order
    $this->actingAs($user)->post('/configurations/online-assistance-types', [
        'name' => 'Custom Form Assistance',
        'price' => '450.00',
        'sort_order' => null,
    ])->assertRedirect('/configurations?tab=online-assistance');

    $this->assertDatabaseHas('online_assistance_types', [
        'name' => 'Custom Form Assistance',
        'sort_order' => 0,
    ]);

    // Expense Category with null sort_order
    $this->actingAs($user)->post('/configurations/expense-categories', [
        'name' => 'Marketing & Ads',
        'sort_order' => null,
    ])->assertRedirect('/configurations?tab=expense-categories');

    $this->assertDatabaseHas('expense_categories', [
        'name' => 'Marketing & Ads',
        'sort_order' => 0,
    ]);

    // PSA Rate with null sort_order
    $this->actingAs($user)->post('/configurations/psa-rates', [
        'document_type' => 'TEST_DOC',
        'name' => 'Test Document',
        'price' => '350.00',
        'officer_fee' => '250.00',
        'sort_order' => null,
    ])->assertRedirect('/configurations?tab=psa-rates');

    $this->assertDatabaseHas('psa_rates', [
        'document_type' => 'TEST_DOC',
        'sort_order' => 0,
    ]);
});

test('an authenticated user can create, update, and delete a psa rate', function () {
    $user = User::factory()->create();

    // Create
    $response = $this->actingAs($user)->post('/configurations/psa-rates', [
        'document_type' => 'AFFIDAVIT',
        'name' => 'Affidavit Authentication',
        'price' => '500.00',
        'officer_fee' => '350.00',
        'is_active' => true,
        'sort_order' => 10,
    ]);

    $response->assertRedirect('/configurations?tab=psa-rates');
    $this->assertDatabaseHas('psa_rates', [
        'document_type' => 'AFFIDAVIT',
        'name' => 'Affidavit Authentication',
        'price' => 500.00,
        'officer_fee' => 350.00,
    ]);

    $rate = PsaRate::where('document_type', 'AFFIDAVIT')->first();
    expect($rate->net_income)->toEqual(150.00);

    // Update
    $updateResponse = $this->actingAs($user)->put("/configurations/psa-rates/{$rate->id}", [
        'document_type' => 'AFFIDAVIT',
        'name' => 'Affidavit of Support',
        'price' => '550.00',
        'officer_fee' => '380.00',
        'is_active' => false,
        'sort_order' => 12,
    ]);

    $updateResponse->assertRedirect('/configurations?tab=psa-rates');
    $this->assertDatabaseHas('psa_rates', [
        'id' => $rate->id,
        'name' => 'Affidavit of Support',
        'price' => 550.00,
        'officer_fee' => 380.00,
        'is_active' => false,
    ]);

    // Delete
    $deleteResponse = $this->actingAs($user)->delete("/configurations/psa-rates/{$rate->id}");
    $deleteResponse->assertRedirect('/configurations?tab=psa-rates');
    $this->assertDatabaseMissing('psa_rates', ['id' => $rate->id]);
});

test('an authenticated user cannot delete a psa rate if used in transactions', function () {
    $user = User::factory()->create();
    $rate = PsaRate::where('document_type', 'BC')->first();

    Transaction::create([
        'type' => Transaction::TYPE_PSA,
        'psa_document_type' => 'BC',
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-08-28',
        'customer_name' => 'Juan Dela Cruz',
        'amount' => '320.00',
        'third_party_payment' => '260.00',
        'ddn_amount' => '60.00',
    ]);

    $response = $this->actingAs($user)->delete("/configurations/psa-rates/{$rate->id}");
    $response->assertRedirect('/configurations?tab=psa-rates');
    $this->assertDatabaseHas('psa_rates', ['id' => $rate->id]);
});

test('an authenticated user cannot create a psa rate with duplicate document_type', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/configurations/psa-rates', [
        'document_type' => 'BC',
        'name' => 'Duplicate Birth Certificate',
        'price' => '320.00',
        'officer_fee' => '260.00',
    ]);

    $response->assertSessionHasErrors(['document_type']);
});

test('officer_fee must not exceed price when saving psa rate', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/configurations/psa-rates', [
        'document_type' => 'INVALID_FEE',
        'name' => 'Invalid Fee Doc',
        'price' => '300.00',
        'officer_fee' => '350.00',
    ]);

    $response->assertSessionHasErrors(['officer_fee']);
});
