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
        DB::table('ins_stc_machines')
            ->whereNull('ip_address')
            ->update(['ip_address' => DB::raw("CONCAT('127.0.0.', id)")]);

        Schema::table('ins_stc_machines', function (Blueprint $table) {
            $table->ipAddress('ip_address')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_stc_machines', function (Blueprint $table) {
            $table->ipAddress('ip_address')->nullable()->change();
        });
    }
};
