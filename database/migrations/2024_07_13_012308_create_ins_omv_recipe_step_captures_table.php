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
        Schema::create('ins_omv_recipe_step_captures', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('ins_omv_recipe_step_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('delay');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_omv_recipe_step_captures');
    }
};
