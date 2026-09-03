<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ExpenseCategorySeeder::class);
});

function expenseData(array $overrides = []): array
{
    $category = ExpenseCategory::query()->first();

    return array_merge([
        'title' => 'Office Internet Bill',
        'expense_category_id' => $category->id,
        'amount' => '1899.00',
        'expense_date' => now()->toDateString(),
        'payment_method' => Expense::PAYMENT_METHOD_GCASH,
        'payee' => 'PLDT Home',
        'reference_number' => 'REF-982342',
        'recurring_frequency' => Expense::FREQUENCY_MONTHLY,
        'notes' => 'Fiber plan payment for current billing cycle.',
    ], $overrides);
}

test('guests cannot access expenses', function () {
    $this->get(route('expenses.index'))->assertRedirect(route('login'));
    $this->get(route('expenses.create'))->assertRedirect(route('login'));
});

test('an authenticated user can view recorded expenses and summary calculations', function () {
    $user = User::factory()->create();
    $cat = ExpenseCategory::query()->first();

    $expense1 = Expense::query()->create(expenseData([
        'amount' => '1000.00',
        'payment_method' => 'cash',
        'expense_category_id' => $cat->id,
    ]));

    $expense2 = Expense::query()->create(expenseData([
        'title' => 'Printer Toner',
        'amount' => '2500.00',
        'payment_method' => 'gcash',
        'expense_category_id' => $cat->id,
    ]));

    $this->actingAs($user)
        ->get(route('expenses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/Index')
            ->has('expenses.data', 2)
            ->where('summary.total_expenses', 3500)
            ->where('summary.total_cash', 1000)
            ->where('summary.total_gcash', 2500)
            ->where('summary.top_category.name', $cat->name)
        );
});

test('an authenticated user can record a valid expense', function () {
    $user = User::factory()->create();
    $category = ExpenseCategory::query()->where('name', 'Utilities')->first();

    $payload = expenseData([
        'title' => 'Water Bill',
        'expense_category_id' => $category->id,
        'amount' => '450.50',
        'payment_method' => 'cash',
    ]);

    $response = $this->actingAs($user)->post(route('expenses.store'), $payload);

    $expense = Expense::query()->sole();

    $response->assertRedirect(route('expenses.show', $expense));
    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'title' => 'Water Bill',
        'expense_category_id' => $category->id,
        'amount' => '450.50',
        'payment_method' => 'cash',
        'recurring_frequency' => 'monthly',
    ]);
});

test('expense requires essential fields (title, category, amount, date, payment method)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('expenses.create'))
        ->post(route('expenses.store'), [
            'title' => '',
            'expense_category_id' => '',
            'amount' => '',
            'expense_date' => '',
            'payment_method' => '',
        ])
        ->assertRedirect(route('expenses.create'))
        ->assertSessionHasErrors(['title', 'expense_category_id', 'amount', 'expense_date', 'payment_method']);

    $this->assertDatabaseCount('expenses', 0);
});

test('amount must be positive with at most two decimal places', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('expenses.create'))
        ->post(route('expenses.store'), expenseData([
            'amount' => '-50.00',
        ]))
        ->assertSessionHasErrors(['amount']);

    $this->actingAs($user)
        ->from(route('expenses.create'))
        ->post(route('expenses.store'), expenseData([
            'amount' => '0',
        ]))
        ->assertSessionHasErrors(['amount']);

    $this->actingAs($user)
        ->from(route('expenses.create'))
        ->post(route('expenses.store'), expenseData([
            'amount' => '100.999',
        ]))
        ->assertSessionHasErrors(['amount']);

    $this->assertDatabaseCount('expenses', 0);
});

test('expense date cannot be in the future', function () {
    $user = User::factory()->create();
    $futureDate = now()->addDays(2)->toDateString();

    $this->actingAs($user)
        ->from(route('expenses.create'))
        ->post(route('expenses.store'), expenseData([
            'expense_date' => $futureDate,
        ]))
        ->assertRedirect(route('expenses.create'))
        ->assertSessionHasErrors(['expense_date']);

    $this->assertDatabaseCount('expenses', 0);
});

test('an authenticated user can view, update, and soft-delete an expense', function () {
    $user = User::factory()->create();
    $expense = Expense::query()->create(expenseData());

    // Show
    $this->actingAs($user)
        ->get(route('expenses.show', $expense))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/Show')
            ->where('expense.id', $expense->id)
            ->where('expense.title', 'Office Internet Bill')
        );

    // Edit view
    $this->actingAs($user)
        ->get(route('expenses.edit', $expense))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/Edit')
            ->where('expense.id', $expense->id)
        );

    // Update
    $this->actingAs($user)
        ->put(route('expenses.update', $expense), expenseData([
            'title' => 'Updated Internet Bill',
            'amount' => '2099.00',
            'payment_method' => 'bank_transfer',
        ]))
        ->assertRedirect(route('expenses.show', $expense));

    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'title' => 'Updated Internet Bill',
        'amount' => '2099.00',
        'payment_method' => 'bank_transfer',
    ]);

    // Destroy (Soft delete)
    $this->actingAs($user)
        ->delete(route('expenses.destroy', $expense))
        ->assertRedirect(route('expenses.index'));

    $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
});

test('soft-deleted expenses are excluded from summaries and index list', function () {
    $user = User::factory()->create();
    $active = Expense::query()->create(expenseData(['amount' => '500.00']));
    $deleted = Expense::query()->create(expenseData(['amount' => '1000.00']));
    $deleted->delete();

    $this->actingAs($user)
        ->get(route('expenses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/Index')
            ->has('expenses.data', 1)
            ->where('expenses.data.0.id', $active->id)
            ->where('summary.total_expenses', 500)
        );
});

test('an authenticated user can access create view with duplicate preloading', function () {
    $user = User::factory()->create();
    $source = Expense::query()->create(expenseData(['title' => 'Source Template Expense']));

    $this->actingAs($user)
        ->get(route('expenses.create', ['duplicate_id' => $source->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/Create')
            ->where('sourceExpense.id', $source->id)
            ->where('sourceExpense.title', 'Source Template Expense')
        );
});

test('an authenticated user can create a new category via quick-add', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('expense-categories.store'), [
            'name' => 'Software Licenses',
            'description' => 'SaaS and tool subscriptions',
        ]);

    $response->assertCreated()
        ->assertJsonFragment([
            'name' => 'Software Licenses',
        ]);

    $this->assertDatabaseHas('expense_categories', [
        'name' => 'Software Licenses',
        'is_active' => true,
    ]);
});

test('category name must be unique', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('expense-categories.store'), [
            'name' => 'Utilities',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('deleting a category with associated expenses is restricted', function () {
    $category = ExpenseCategory::query()->first();
    Expense::query()->create(expenseData(['expense_category_id' => $category->id]));

    expect(fn () => $category->delete())->toThrow(QueryException::class);
});
