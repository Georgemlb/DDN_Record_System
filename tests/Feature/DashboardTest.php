<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('kpis')
            ->has('salesTrend')
            ->has('expensesByCategory')
            ->has('recentTransactions')
            ->where('period', 'this_month')
        );
});

test('it accurately calculates KPIs for the default period', function () {
    $user = User::factory()->create();
    $now = Carbon::create(2026, 9, 15, 12, 0, 0);
    Carbon::setTestNow($now);

    // Within current month
    Transaction::query()->create([
        'type' => Transaction::TYPE_AIRLINE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-09-10',
        'customer_name' => 'John Doe',
        'pax' => 1,
        'amount' => 5000.00,
        'ddn_amount' => 5000.00,
    ]);

    Transaction::query()->create([
        'type' => Transaction::TYPE_TRANSLATION,
        'payment_method' => Transaction::PAYMENT_METHOD_GCASH,
        'transaction_date' => '2026-09-12',
        'customer_name' => 'Jane Smith',
        'pax' => 1,
        'amount' => 3000.00,
        'third_party_payment' => 1000.00,
        'ddn_amount' => 2000.00,
    ]);

    // Outside current month (should be excluded)
    Transaction::query()->create([
        'type' => Transaction::TYPE_PRINTING,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => '2026-08-25',
        'customer_name' => 'Old Customer',
        'pax' => 1,
        'amount' => 500.00,
        'ddn_amount' => 500.00,
    ]);

    $category = ExpenseCategory::query()->create([
        'name' => 'Office Supplies',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    // Active expense in current month
    Expense::query()->create([
        'title' => 'Paper and ink',
        'expense_category_id' => $category->id,
        'amount' => 1500.00,
        'expense_date' => '2026-09-08',
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'recurring_frequency' => Expense::FREQUENCY_NONE,
    ]);

    // Expense outside current month
    Expense::query()->create([
        'title' => 'August Electricity',
        'expense_category_id' => $category->id,
        'amount' => 2000.00,
        'expense_date' => '2026-08-30',
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'recurring_frequency' => Expense::FREQUENCY_NONE,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('kpis.total_sales', 8000)
            ->where('kpis.total_ddn', 7000)
            ->where('kpis.total_expenses', 1500)
            ->where('kpis.net_profit', 5500) // 7000 - 1500
            ->where('kpis.total_cash', 5000)
            ->where('kpis.total_gcash', 3000)
        );

    Carbon::setTestNow();
});

test('it correctly filters data by period', function (string $period, string $txDate, string $expDate, int $expectedSales) {
    $user = User::factory()->create();
    $now = Carbon::create(2026, 9, 15, 12, 0, 0); // Tuesday
    Carbon::setTestNow($now);

    $category = ExpenseCategory::query()->create([
        'name' => 'Utilities',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Transaction::query()->create([
        'type' => Transaction::TYPE_AIRLINE,
        'payment_method' => Transaction::PAYMENT_METHOD_CASH,
        'transaction_date' => $txDate,
        'customer_name' => 'Periodic Test',
        'pax' => 1,
        'amount' => $expectedSales,
        'ddn_amount' => $expectedSales,
    ]);

    Expense::query()->create([
        'title' => 'Utility bill',
        'expense_category_id' => $category->id,
        'amount' => 400.00,
        'expense_date' => $expDate,
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'recurring_frequency' => Expense::FREQUENCY_NONE,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['period' => $period]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('period', $period)
            ->where('kpis.total_sales', $expectedSales)
            ->where('kpis.total_expenses', 400)
        );

    Carbon::setTestNow();
})->with([
    ['today', '2026-09-15', '2026-09-15', 1200],
    ['this_week', '2026-09-16', '2026-09-16', 2200],
    ['last_month', '2026-08-10', '2026-08-10', 3200],
    ['this_year', '2026-03-01', '2026-03-01', 4200],
]);

test('it excludes soft-deleted expenses from totals and category breakdowns', function () {
    $user = User::factory()->create();
    $now = Carbon::create(2026, 9, 15, 12, 0, 0);
    Carbon::setTestNow($now);

    $category = ExpenseCategory::query()->create([
        'name' => 'Software',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $activeExpense = Expense::query()->create([
        'title' => 'Active Subscription',
        'expense_category_id' => $category->id,
        'amount' => 1000.00,
        'expense_date' => '2026-09-05',
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'recurring_frequency' => Expense::FREQUENCY_NONE,
    ]);

    $deletedExpense = Expense::query()->create([
        'title' => 'Deleted Subscription',
        'expense_category_id' => $category->id,
        'amount' => 2500.00,
        'expense_date' => '2026-09-06',
        'payment_method' => Expense::PAYMENT_METHOD_CASH,
        'recurring_frequency' => Expense::FREQUENCY_NONE,
    ]);
    $deletedExpense->delete();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('kpis.total_expenses', 1000)
            ->has('expensesByCategory', 1)
            ->where('expensesByCategory.0.total', 1000)
        );

    Carbon::setTestNow();
});

test('it handles empty database state without errors', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('kpis.total_sales', 0)
            ->where('kpis.total_ddn', 0)
            ->where('kpis.total_expenses', 0)
            ->where('kpis.net_profit', 0)
            ->where('kpis.total_cash', 0)
            ->where('kpis.total_gcash', 0)
            ->has('salesTrend', 0)
            ->has('expensesByCategory', 0)
            ->has('recentTransactions', 0)
        );
});

test('it returns recent transactions ordered desc by date and id up to 5 items', function () {
    $user = User::factory()->create();

    for ($i = 1; $i <= 7; $i++) {
        Transaction::query()->create([
            'type' => Transaction::TYPE_PRINTING,
            'payment_method' => Transaction::PAYMENT_METHOD_CASH,
            'transaction_date' => "2026-09-0{$i}",
            'customer_name' => "Customer {$i}",
            'pax' => 1,
            'amount' => 100.00 * $i,
            'ddn_amount' => 100.00 * $i,
        ]);
    }

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('recentTransactions', 5)
            ->where('recentTransactions.0.customer_name', 'Customer 7')
            ->where('recentTransactions.4.customer_name', 'Customer 3')
        );
});
