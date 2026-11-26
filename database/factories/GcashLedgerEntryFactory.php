<?php

namespace Database\Factories;

use App\Models\GcashLedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GcashLedgerEntry>
 */
class GcashLedgerEntryFactory extends Factory
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
            'type' => GcashLedgerEntry::TYPE_ADJUSTMENT,
            'amount' => 1000.00,
            'entry_date' => now()->toDateString(),
            'sort_order' => 1,
            'notes' => 'GCash adjustment',
        ];
    }
}
