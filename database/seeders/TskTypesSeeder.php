<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TskTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
