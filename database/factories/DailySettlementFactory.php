<?php

namespace Database\Factories;

use App\Models\DailySettlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailySettlement>
 */
class DailySettlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'settlement_date' => fake()->unique()->date(),
            'user_id' => User::factory(),
            'status' => DailySettlement::STATUS_OPEN,
            'opening_cash' => 1000.00,
            'cash_in' => 0,
            'cash_out' => 0,
            'expected_cash' => 1000.00,
            'opening_gcash' => 500.00,
            'gcash_in' => 0,
            'gcash_out' => 0,
            'expected_gcash' => 500.00,
            'total_gcash' => 0,
            'total_bank_transfer' => 0,
            'total_sales' => 0,
            'total_ddn_earnings' => 0,
            'opened_at' => now(),
        ];
    }
}
