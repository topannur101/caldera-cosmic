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
            if (!Schema::hasColumn('ins_ctc_metrics', 'recipe_std_min')) {
                $table->decimal('recipe_std_min', 4, 2)->nullable()->after('t_balance');
            }
            if (!Schema::hasColumn('ins_ctc_metrics', 'recipe_std_mid')) {
                $table->decimal('recipe_std_mid', 4, 2)->nullable();
            }
            if (!Schema::hasColumn('ins_ctc_metrics', 'recipe_std_max')) {
                $table->decimal('recipe_std_max', 4, 2)->nullable();
            }
            if (!Schema::hasColumn('ins_ctc_metrics', 'actual_std_min')) {
                $table->decimal('actual_std_min', 4, 2)->nullable();
            }
            if (!Schema::hasColumn('ins_ctc_metrics', 'actual_std_mid')) {
                $table->decimal('actual_std_mid', 4, 2)->nullable();
            }
            if (!Schema::hasColumn('ins_ctc_metrics', 'actual_std_max')) {
                $table->decimal('actual_std_max', 4, 2)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_ctc_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'recipe_std_min',
                'recipe_std_mid',
                'recipe_std_max',
                'actual_std_min',
                'actual_std_mid',
                'actual_std_max',
            ]);
        });
    }
};