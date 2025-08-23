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
            $table->boolean('is_active')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_rdc_machines', function (Blueprint $table) {
            if (Schema::hasColumn('ins_rdc_machines', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('ins_rdc_machines', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
