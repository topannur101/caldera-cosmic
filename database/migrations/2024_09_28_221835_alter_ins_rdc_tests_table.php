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
        Schema::table('ins_rdc_tests', function (Blueprint $table) {
            $table->decimal('tc10_min', 5, 2)->nullable();
            $table->decimal('tc10_max', 5, 2)->nullable();
            $table->decimal('tc90_min', 5, 2)->nullable();
            $table->decimal('tc90_max', 5, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_rdc_tests', function (Blueprint $table) {
            $table->dropColumn('tc10_min');
            $table->dropColumn('tc10_max');
            $table->dropColumn('tc90_min');
            $table->dropColumn('tc90_max');
        });
    }
};
