<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Seed CMS default content if the package is installed
        if (class_exists(\NetServa\Cms\Database\Seeders\NetServaCmsSeeder::class)) {
            $this->call(\NetServa\Cms\Database\Seeders\NetServaCmsSeeder::class);
        }
    }
}
