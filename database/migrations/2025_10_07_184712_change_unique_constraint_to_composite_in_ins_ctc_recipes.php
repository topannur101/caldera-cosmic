<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ins_ctc_recipes', function (Blueprint $table) {
            // // Drop unique constraint pada name saja
            // $table->dropUnique('ins_ctc_recipes_name_unique');
            
            // // Tambah composite unique constraint
            // // Kombinasi name + component_model + og_rs harus unique
            // $table->unique(['name', 'component_model', 'og_rs'], 'recipe_unique_combination');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_ctc_recipes', function (Blueprint $table) {
            // // Drop composite unique
            // $table->dropUnique('recipe_unique_combination');
            
            // // Kembalikan unique pada name saja
            // $table->unique('name');
        });
    }
};
