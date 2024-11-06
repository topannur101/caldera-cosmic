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
        Schema::table('ins_ldc_hides', function (Blueprint $table) {
            $table->foreignId('ins_ldc_quota_id')->nullable()->after('ins_ldc_group_id');

            $table->index('ins_ldc_quota_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_ldc_hides', function (Blueprint $table) {
            $table->dropColumn('ins_ldc_quota_id'); 
        });
    }
};
