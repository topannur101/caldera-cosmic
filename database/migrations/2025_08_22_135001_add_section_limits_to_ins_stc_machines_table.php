<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ins_stc_machines', function (Blueprint $table) {
            // Add section limits columns after at_adjust_strength
            $table->json('section_limits_high')->nullable()->after('at_adjust_strength');
            $table->json('section_limits_low')->nullable()->after('section_limits_high');
        });

        // Set default values for existing machines
        DB::table('ins_stc_machines')->update([
            'section_limits_high' => json_encode([83, 78, 73, 68, 63, 58, 53, 48]),
            'section_limits_low' => json_encode([73, 68, 63, 58, 53, 48, 43, 38]),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_stc_machines', function (Blueprint $table) {
            $table->dropColumn(['section_limits_high', 'section_limits_low']);
        });
    }
};
