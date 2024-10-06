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
        Schema::table('ins_omv_metrics', function (Blueprint $table) {
            $table->enum('eval', ['too_soon', 'on_time', 'too_late', 'on_time_manual'])
                  ->change();
        });

        Schema::table('ins_rubber_batches', function (Blueprint $table) {
            $table->enum('omv_eval', ['too_soon', 'on_time', 'too_late', 'on_time_manual'])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_omv_metrics', function (Blueprint $table) {
            $table->enum('eval', ['too_soon', 'on_time', 'too_late'])
                  ->change();
        });
        Schema::table('ins_rubber_batches', function (Blueprint $table) {
            $table->enum('omv_eval', ['too_soon', 'on_time', 'too_late'])->nullable()->change();
        });
    }
};
