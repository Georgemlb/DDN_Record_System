<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 5000);

        return [
            'user_id' => User::factory(),
            'type' => Transaction::TYPE_PRINTING,
            'payment_method' => Transaction::PAYMENT_METHOD_CASH,
            'transaction_date' => now()->toDateString(),
            'customer_name' => fake()->name(),
            'amount' => $amount,
            'amount_paid' => $amount,
            'balance' => 0,
            'payment_status' => Transaction::PAYMENT_STATUS_PAID,
            'ddn_amount' => $amount,
            'third_party_payment' => 0,
            'employee_share' => 0,
        ];
    }
}
