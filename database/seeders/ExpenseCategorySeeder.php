<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Utilities',
                'description' => 'Electricity, water, internet, and communication bills.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Office Supplies',
                'description' => 'Stationery, desk supplies, and office consumables.',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Rent',
                'description' => 'Office space and facility lease disbursements.',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Equipment & Hardware',
                'description' => 'Computers, printers, machinery purchases, and repairs.',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Travel & Transportation',
                'description' => 'Fuel, transport fares, delivery, and courier expenses.',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Printing Supplies',
                'description' => 'Printing paper, inks, toners, and lamination film.',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Miscellaneous',
                'description' => 'General and incidental operational expenses.',
                'is_active' => true,
                'sort_order' => 7,
            ],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::query()->firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
