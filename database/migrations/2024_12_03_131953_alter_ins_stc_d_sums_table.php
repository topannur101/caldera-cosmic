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
            // Rename columns
            $table->renameColumn('start_time', 'started_at');
            $table->renameColumn('end_time', 'ended_at');
            $table->renameColumn('preheat_temp', 'preheat');
            $table->renameColumn('postheat_temp', 'postheat');

            // Drop unwanted columns
            $table->dropColumn(['z_1_temp', 'z_2_temp', 'z_3_temp', 'z_4_temp']);

            // Add new columns with default value 0
            $table->decimal('section_1', 3, 1)->default(0);
            $table->decimal('section_2', 3, 1)->default(0);
            $table->decimal('section_3', 3, 1)->default(0);
            $table->decimal('section_4', 3, 1)->default(0);
            $table->decimal('section_5', 3, 1)->default(0);
            $table->decimal('section_6', 3, 1)->default(0);
            $table->decimal('section_7', 3, 1)->default(0);
            $table->decimal('section_8', 3, 1)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_stc_d_sums', function (Blueprint $table) {
            // Rename columns back to original names
            $table->renameColumn('started_at', 'start_time');
            $table->renameColumn('ended_at', 'end_time');
            $table->renameColumn('preheat', 'preheat_temp');
            $table->renameColumn('postheat', 'postheat_temp');

            // Re-add deleted columns
            $table->decimal('z_1_temp', 3, 1)->default(0);
            $table->decimal('z_2_temp', 3, 1)->default(0);
            $table->decimal('z_3_temp', 3, 1)->default(0);
            $table->decimal('z_4_temp', 3, 1)->default(0);

            // Drop newly added columns
            $table->dropColumn([
                'section_1',
                'section_2',
                'section_3',
                'section_4',
                'section_5',
                'section_6',
                'section_7',
                'section_8'
            ]);
        });
    }
};
