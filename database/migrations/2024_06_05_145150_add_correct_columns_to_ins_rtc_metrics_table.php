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
        Schema::table('ins_rtc_metrics', function (Blueprint $table) {
            $table->decimal('push_left', 4, 2)->nullable();
            $table->decimal('push_right', 4, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_rtc_metrics', function (Blueprint $table) {
            $table->dropColumn('push_left');
            $table->dropColumn('push_right');
        });
    }
};
