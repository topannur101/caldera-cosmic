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
        Schema::table('ins_stc_d_sums', function (Blueprint $table) {
            // Add at_values JSON column after svp_values
            // Stores an array of 3 numeric elements: [previous_at, current_at, delta_at]
            $table->json('at_values')->nullable()->after('svp_values');
        });

        // Update existing records to have default [0,0,0] values
        DB::table('ins_stc_d_sums')->update(['at_values' => json_encode([0, 0, 0])]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_stc_d_sums', function (Blueprint $table) {
            $table->dropColumn('at_values');
        });
    }
};
