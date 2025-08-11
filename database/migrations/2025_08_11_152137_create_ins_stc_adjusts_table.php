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
        Schema::create('ins_stc_adjusts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ins_stc_d_sum_id')->constrained()->onDelete('cascade');
            $table->decimal('current_temp', 4, 1); // Current ambient temperature from sensor
            $table->decimal('delta_temp', 4, 1); // Difference between current and baseline
            $table->json('sv_before'); // SV values before adjustment (array of 8)
            $table->json('sv_after'); // SV values after adjustment (array of 8)
            $table->boolean('adjustment_applied')->default(false); // Whether adjustment was actually sent to machine
            $table->string('adjustment_reason')->nullable(); // Reason for adjustment or failure
            $table->timestamps();
            
            // Index for performance
            $table->index('ins_stc_d_sum_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_stc_adjusts');
    }
};