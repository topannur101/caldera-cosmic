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
        Schema::create('ins_rtc_batches', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('ins_rtc_n_recipe_id')->constrained();
            $table->foreignId('ins_rtc_device_id')->constrained();
            $table->timestamp('started_at');
            $table->timestamp('ended_at');

            $table->json('data');
            // {0|1, { 3.21, 0|1|2 }, { 3.21, 0|1|2 } }
            // 0: is_correcting off, 1: is_correcting on
            // 0: null, 1: thin, 2: thick

            $table->index('ins_rtc_recipe_id');
            $table->index('ins_rtc_device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_rtc_batches');
    }

        // public function up(): void
    // {
    //     Schema::create('ins_rtc_clumps', function (Blueprint $table) {
    //         $table->id();
    //         $table->timestamps();

    //         $table->foreignId('ins_rtc_recipe_id')->nullable()->constrained()->nullOnDelete();
    //         $table->foreignId('ins_rtc_device_id')->constrained()->cascadeOnDelete();

    //         $table->index('ins_rtc_recipe_id');
    //         $table->index('ins_rtc_device_id');
    //     });
    // }

    // /**
    //  * Reverse the migrations.
    //  */
    // public function down(): void
    // {
    //     Schema::dropIfExists('ins_rtc_clumps');
    // }
};
