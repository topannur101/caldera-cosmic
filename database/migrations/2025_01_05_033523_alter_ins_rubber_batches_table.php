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
        Schema::table('ins_rubber_batches', function (Blueprint $table) {
            $table->dropColumn(['rdc_eval', 'omv_eval']);
            $table->boolean('rdc_queue')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('ins_rubber_batches', function (Blueprint $table) {
            // Note: Adding dropped columns back requires specifying their type.
            $table->string('rdc_eval')->nullable();
            $table->string('omv_eval')->nullable();
        });
    }
};
