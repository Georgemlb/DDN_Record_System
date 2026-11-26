<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'expense_category_id' => fn () => ExpenseCategory::query()->first()->id ?? ExpenseCategory::create([
                'name' => 'Miscellaneous',
                'is_active' => true,
                'sort_order' => 1,
            ])->id,
            'amount' => fake()->randomFloat(2, 50, 2000),
            'expense_date' => now()->toDateString(),
            'payment_method' => Expense::PAYMENT_METHOD_CASH,
            'payee' => fake()->company(),
            'recurring_frequency' => 'none',
            'is_personal' => false,
            'reimbursement_status' => Expense::REIMBURSEMENT_STATUS_NONE,
        ];
    }
}
