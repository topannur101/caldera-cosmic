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
        Schema::create('ins_rtc_sheets', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('ins_rtc_recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ins_rtc_device_id')->constrained()->cascadeOnDelete();

            $table->index('ins_rtc_recipe_id');
            $table->index('ins_rtc_device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_rtc_sheets');
    }
};
