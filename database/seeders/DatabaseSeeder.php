<?php

namespace Database\Seeders;

use App\Models\TskType;
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

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $types = [
            'Meeting',
            'Others', 
            'Project Improvement',
            'Report',
            'TPM',
            'Training'
        ];

        foreach ($types as $type) {
            TskType::create([
                'name' => $type,
                'is_active' => true,
            ]);
        }
    }
}
