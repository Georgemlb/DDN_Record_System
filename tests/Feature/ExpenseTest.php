<?php

use App\Models\DailySettlement;
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

test('expense automatically associates authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('expenses.store'), expenseData());

    $expense = Expense::query()->latest('id')->first();
    expect($expense->user_id)->toBe($user->id);
});

test('soft-deleted expenses can be viewed in trash and restored', function () {
    $user = User::factory()->create();
    $expense = Expense::query()->create(expenseData());

    $expense->delete();

    // Not visible in active index
    $this->actingAs($user)
        ->get(route('expenses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/Index')
            ->has('expenses.data', 0)
            ->where('counts.trash', 1)
        );

    // Visible in trash index
    $this->actingAs($user)
        ->get(route('expenses.index', ['status' => 'trash']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/Index')
            ->has('expenses.data', 1)
            ->where('expenses.data.0.id', $expense->id)
        );

    // Restore expense
    $this->actingAs($user)
        ->post(route('expenses.restore', $expense))
        ->assertRedirect();

    expect($expense->fresh()->trashed())->toBeFalse();
});

test('can record a personal expense to be reimbursed', function () {
    $user = User::factory()->create();
    $cat = ExpenseCategory::query()->first();

    $response = $this->actingAs($user)->post(route('expenses.store'), expenseData([
        'title' => 'Shopee Gel Personal Item',
        'amount' => '750.00',
        'payment_method' => Expense::PAYMENT_METHOD_GCASH,
        'payee' => 'Gel',
        'is_personal' => true,
    ]));

    $expense = Expense::query()->latest('id')->first();
    $response->assertRedirect(route('expenses.show', $expense));

    expect($expense->is_personal)->toBeTrue()
        ->and($expense->reimbursement_status)->toBe(Expense::REIMBURSEMENT_STATUS_PENDING)
        ->and($expense->reimbursed_at)->toBeNull();
});

test('can reimburse a pending personal expense and update daily settlement metrics', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $cat = ExpenseCategory::query()->first();

    $expenseDate = now()->subDays(2)->toDateString();
    $reimbursementDate = now()->toDateString();

    // Create personal expense disbursed 2 days ago via GCash
    $expense = Expense::query()->create(expenseData([
        'title' => 'Shopee Gel Order',
        'amount' => '500.00',
        'expense_date' => $expenseDate,
        'payment_method' => Expense::PAYMENT_METHOD_GCASH,
        'payee' => 'Gel',
        'is_personal' => true,
        'reimbursement_status' => Expense::REIMBURSEMENT_STATUS_PENDING,
    ]));

    // Reimbursed today with Cash
    $this->actingAs($user)
        ->post(route('expenses.reimburse', $expense), [
            'reimbursement_method' => Expense::PAYMENT_METHOD_CASH,
            'reimbursed_at' => $reimbursementDate,
            'reimbursement_notes' => 'Repaid in cash by Gel to drawer',
        ])
        ->assertRedirect();

    $expense->refresh();
    expect($expense->reimbursement_status)->toBe(Expense::REIMBURSEMENT_STATUS_REIMBURSED)
        ->and($expense->reimbursed_at->toDateString())->toBe($reimbursementDate)
        ->and($expense->reimbursement_method)->toBe(Expense::PAYMENT_METHOD_CASH)
        ->and($expense->reimbursement_notes)->toBe('Repaid in cash by Gel to drawer');

    // Check DailySettlement calculateMetricsForDate for reimbursementDate
    $metrics = DailySettlement::calculateMetricsForDate($reimbursementDate);
    // Cash in must include the 500 cash repayment
    expect($metrics['cash_in'])->toBe(500.0)
        // And sales/earnings must NOT be inflated
        ->and($metrics['total_sales'])->toBe(0.0)
        ->and($metrics['total_ddn_earnings'])->toBe(0.0);
});

test('expense index filters by personal pending and reimbursed tabs', function () {
    $user = User::factory()->create();
    $cat = ExpenseCategory::query()->first();

    // 1 regular business expense
    Expense::query()->create(expenseData([
        'title' => 'Bond Paper',
        'is_personal' => false,
    ]));

    // 1 pending personal expense
    Expense::query()->create(expenseData([
        'title' => 'Shopee Gel Pending',
        'is_personal' => true,
        'reimbursement_status' => Expense::REIMBURSEMENT_STATUS_PENDING,
    ]));

    // 1 reimbursed personal expense
    Expense::query()->create(expenseData([
        'title' => 'Shopee Gel Repaid',
        'is_personal' => true,
        'reimbursement_status' => Expense::REIMBURSEMENT_STATUS_REIMBURSED,
        'reimbursed_at' => now()->toDateString(),
        'reimbursement_method' => Expense::PAYMENT_METHOD_CASH,
    ]));

    // Filter personal_pending
    $this->actingAs($user)
        ->get(route('expenses.index', ['status' => 'personal_pending']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/Index')
            ->has('expenses.data', 1)
            ->where('expenses.data.0.title', 'Shopee Gel Pending')
            ->where('counts.personal_pending', 1)
            ->where('counts.personal_reimbursed', 1)
            ->where('counts.business', 1)
        );

    // Filter personal_reimbursed
    $this->actingAs($user)
        ->get(route('expenses.index', ['status' => 'personal_reimbursed']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/Index')
            ->has('expenses.data', 1)
            ->where('expenses.data.0.title', 'Shopee Gel Repaid')
        );

    // Filter business
    $this->actingAs($user)
        ->get(route('expenses.index', ['status' => 'business']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/Index')
            ->has('expenses.data', 1)
            ->where('expenses.data.0.title', 'Bond Paper')
        );
});

test('can update an expense whose category was later deactivated', function () {
    $user = User::factory()->create();
    $category = ExpenseCategory::query()->first();

    $expense = Expense::query()->create(expenseData([
        'title' => 'Legacy Category Expense',
        'expense_category_id' => $category->id,
        'amount' => '350.00',
    ]));

    // Deactivate category after expense was recorded
    $category->update(['is_active' => false]);

    $this->actingAs($user)
        ->put(route('expenses.update', $expense), expenseData([
            'title' => 'Updated Legacy Category Expense',
            'expense_category_id' => $category->id,
            'amount' => '425.00',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('expenses.show', $expense));

    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'title' => 'Updated Legacy Category Expense',
        'expense_category_id' => $category->id,
        'amount' => '425.00',
    ]);
});
