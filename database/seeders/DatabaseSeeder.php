<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Tech Admin',
                'email' => 'admin@ddn.com',
                'role' => User::ROLE_TECH_ADMIN,
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );

        $this->call([
            BaggageOptionSeeder::class,
            OnlineAssistanceTypeSeeder::class,
            ExpenseCategorySeeder::class,
        ]);
    }
}
