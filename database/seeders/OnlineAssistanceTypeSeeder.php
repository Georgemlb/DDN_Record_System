<?php

namespace Database\Seeders;

use App\Models\OnlineAssistanceType;
use Illuminate\Database\Seeder;

class OnlineAssistanceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Basic Online Assistance',
                'price' => 500.00,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Standard Online Assistance',
                'price' => 800.00,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Premium Online Assistance',
                'price' => 1000.00,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($types as $type) {
            OnlineAssistanceType::query()->firstOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}

