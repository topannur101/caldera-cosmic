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
            // Tambah kolom component_model setelah name
            $table->string('component_model', 20)->after('name')->nullable();
            
            // Hapus kolom priority dan recommended_for_models
            $table->dropColumn(['priority', 'recommended_for_models']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_ctc_recipes', function (Blueprint $table) {
            // Kembalikan kolom yang dihapus
            $table->integer('priority')->default(1);
            $table->json('recommended_for_models')->nullable();
            
            // Hapus kolom component_model
            $table->dropColumn('component_model');
        });
    }
};