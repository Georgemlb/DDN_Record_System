<?php

namespace Database\Seeders;

use App\Models\BaggageOption;
use Illuminate\Database\Seeder;

class BaggageOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $options = [
            [
                'label' => 'No Additional Baggage',
                'weight_kg' => 0,
                'price' => 0.00,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'label' => '+10 KG',
                'weight_kg' => 10,
                'price' => 500.00,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'label' => '+20 KG',
                'weight_kg' => 20,
                'price' => 1000.00,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'label' => '+30 KG',
                'weight_kg' => 30,
                'price' => 1500.00,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'label' => '+40 KG',
                'weight_kg' => 40,
                'price' => 2000.00,
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($options as $option) {
            BaggageOption::query()->firstOrCreate(
                ['label' => $option['label']],
                $option
            );
        }
    }
}

