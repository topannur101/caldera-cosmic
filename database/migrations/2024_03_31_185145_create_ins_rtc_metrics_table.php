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
        Schema::create('ins_rtc_metrics', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ins_rtc_clump_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_correcting');
            $table->enum('action_left', ['thick','thin'])->nullable();   
            $table->enum('action_right', ['thick','thin'])->nullable();   
            $table->decimal('sensor_left', 4, 2); // 12.34
            $table->decimal('sensor_right', 4, 2); //  6 > 6.0... 6.5808... 6.58

            $table->datetime('dt_client');

            $table->index('ins_rtc_clump_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_rtc_metrics');
    }
};
