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
        Schema::table('ins_ctc_metrics', function (Blueprint $table) {
            // Add correction metrics after is_auto column
            $table->tinyInteger('correction_uptime')->default(0)->after('is_auto')
                ->comment('Percentage of time auto-correction was running (0-100)');
            
            $table->tinyInteger('correction_rate')->default(0)->after('correction_uptime')
                ->comment('Percentage of actual correction actions taken (0-100)');
            
            $table->integer('correction_left')->default(0)->after('correction_rate')
                ->comment('Count of left-side correction actions');
            
            $table->integer('correction_right')->default(0)->after('correction_left')
                ->comment('Count of right-side correction actions');

            // Add indexes for performance
            $table->index('correction_uptime');
            $table->index('correction_rate');
            $table->index(['correction_left', 'correction_right']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_ctc_metrics', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['ins_ctc_metrics_correction_uptime_index']);
            $table->dropIndex(['ins_ctc_metrics_correction_rate_index']);
            $table->dropIndex(['ins_ctc_metrics_correction_left_correction_right_index']);
            
            // Drop columns
            $table->dropColumn([
                'correction_uptime',
                'correction_rate', 
                'correction_left',
                'correction_right'
            ]);
        });
    }
};
