<?php

namespace Database\Factories;

use App\Models\FlightPortalDeposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlightPortalDeposit>
 */
class FlightPortalDepositFactory extends Factory
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
            'type' => FlightPortalDeposit::TYPE_TOP_UP,
            'amount' => 5000.00,
            'entry_date' => now()->toDateString(),
            'payment_source' => FlightPortalDeposit::PAYMENT_SOURCE_CASH,
            'sort_order' => 1,
            'notes' => 'Portal top-up',
        ];
    }
}
