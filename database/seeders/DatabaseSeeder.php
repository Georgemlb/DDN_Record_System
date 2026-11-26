<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminName = (string) config('ddn.admin.name', 'Tech Admin');
        $adminUsername = (string) config('ddn.admin.username', 'admin');
        $adminEmail = (string) config('ddn.admin.email', 'admin@ddn.com');
        $rawPassword = config('ddn.admin.password');

        if (app()->isProduction() && (! is_string($rawPassword) || trim($rawPassword) === '' || $rawPassword === 'admin123')) {
            throw new RuntimeException(
                'Set a strong DDN_ADMIN_PASSWORD in your production environment before seeding.',
            );
        }

        $adminPassword = is_string($rawPassword) && trim($rawPassword) !== ''
            ? $rawPassword
            : 'admin123';

        User::firstOrCreate(
            ['username' => $adminUsername],
            [
                'name' => $adminName,
                'email' => $adminEmail,
                'role' => User::ROLE_TECH_ADMIN,
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
            ]
        );

        $this->call([
            OnlineAssistanceTypeSeeder::class,
            ExpenseCategorySeeder::class,
            PsaRateSeeder::class,
            AirlineMarkupSeeder::class,
        ]);
    }
}
