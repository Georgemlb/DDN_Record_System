<?php

namespace Database\Seeders;

use App\Models\PsaRate;
use Illuminate\Database\Seeder;

class PsaRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rates = [
            [
                'document_type' => 'BC',
                'name' => 'Birth Certificate',
                'price' => 320.00,
                'officer_fee' => 260.00,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'document_type' => 'MC',
                'name' => 'Marriage Certificate',
                'price' => 320.00,
                'officer_fee' => 260.00,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'document_type' => 'DC',
                'name' => 'Death Certificate',
                'price' => 320.00,
                'officer_fee' => 260.00,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'document_type' => 'CENOMAR',
                'name' => 'Certificate of No Marriage (CENOMAR)',
                'price' => 400.00,
                'officer_fee' => 310.00,
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($rates as $rate) {
            PsaRate::firstOrCreate(
                ['document_type' => $rate['document_type']],
                $rate
            );
        }
    }
}
