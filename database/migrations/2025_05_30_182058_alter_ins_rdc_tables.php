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
        Schema::table('ins_rdc_machines', function (Blueprint $table) {
            $table->enum('type', ['excel', 'txt'])->default('excel')->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_rdc_machines', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};