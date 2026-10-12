<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'school_id' => '2026-0001',
            'role' => 'admin',
        ]);
        
        $this->call([
            AccountSeeder::class,
            EquipmentSeeder::class,
            EventResourceSeeder::class,
        ]);
    }
}
