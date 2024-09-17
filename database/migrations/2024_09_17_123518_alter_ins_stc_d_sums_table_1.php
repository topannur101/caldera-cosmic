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
        Schema::table('ins_stc_d_sums', function (Blueprint $table) {
            $table->decimal('speed', 4, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_stc_d_sums', function (Blueprint $table) {
            $table->decimal('speed', 3, 1)->change();
        });
    }
};
