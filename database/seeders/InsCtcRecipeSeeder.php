<?php

namespace Database\Seeders;

use App\Models\InsCtcRecipe;
use Illuminate\Database\Seeder;

class InsCtcRecipeSeeder extends Seeder
{
    public function run()
    {
        $recipes = [
            [
                'name' => 'AF1 GS (ONE COLOR)',
                'og_rs' => 'GS',
                'std_min' => 3.0,
                'std_max' => 3.1,
                'scale' => 1.0,
                'pfc_min' => 3.4,
                'pfc_max' => 3.6,
                'priority' => 1,
                'is_active' => true,
                'recommended_for_models' => ['AF1'],
            ],
            [
                'name' => 'AF1 WS (TWO COLOR)',
                'og_rs' => 'WS',
                'std_min' => 3.0,
                'std_max' => 3.1,
                'scale' => 1.0,
                'pfc_min' => 3.2,
                'pfc_max' => 3.4,
                'priority' => 2,
                'is_active' => true,
                'recommended_for_models' => ['AF1'],
            ],
            // ... tambahkan lainnya
        ];

        foreach ($recipes as $recipe) {
            InsCtcRecipe::updateOrCreate(
                ['name' => $recipe['name']], // Cari berdasarkan nama
                $recipe // Update atau create
            );
        }
    }
}