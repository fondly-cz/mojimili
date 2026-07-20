<?php

namespace Database\Seeders;

use App\Enums\UserRole;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ServiceSeeder::class);
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Fondly Admin',
            'email' => 'spoluprace@fondly.cz',
            'password' => Hash::make('admin'),
            'role' => UserRole::ADMIN,
        ]);

        $this->call([
            CompanySeeder::class,
            ServiceSeeder::class,
            CalculationSeeder::class,
        ]);
    }
}
