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
        Schema::table('ins_ppm_components', function (Blueprint $table) {
            $table->string('material_name')->nullable()->after('material_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_ppm_components', function (Blueprint $table) {
            $table->dropColumn('material_name');
        });
    }
};
