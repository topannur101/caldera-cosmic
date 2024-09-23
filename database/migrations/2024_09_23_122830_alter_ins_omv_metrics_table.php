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
            $table->json('data')->nullable()->after('end_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_omv_metrics', function (Blueprint $table) {
            $table->dropColumn('data'); // Remove 'data' column if rolled back
        });
    }
};
