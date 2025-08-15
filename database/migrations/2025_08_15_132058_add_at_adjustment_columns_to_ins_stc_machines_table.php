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
        Schema::table('ins_stc_machines', function (Blueprint $table) {
            $table->boolean('is_at_adjusted')->default(false)->after('ip_address');
            $table->json('at_adjust_strength')->nullable()->after('is_at_adjusted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_stc_machines', function (Blueprint $table) {
            $table->dropColumn(['is_at_adjusted', 'at_adjust_strength']);
        });
    }
};
