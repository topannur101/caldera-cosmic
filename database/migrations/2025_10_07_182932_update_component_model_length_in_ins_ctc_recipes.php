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
            // Update length dari 20 ke 100 dan set NULLABLE
            $table->string('component_model', 100)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_ctc_recipes', function (Blueprint $table) {
            // Kembalikan ke 20
            $table->string('component_model', 20)->change();
        });
    }
};