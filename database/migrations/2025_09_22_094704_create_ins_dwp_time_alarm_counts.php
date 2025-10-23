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
        Schema::create('ins_dwp_time_alarm_counts', function (Blueprint $table) {
            $table->id();
            $table->string('line'); // Line identifier (globally unique, uppercase)
            $table->integer('cumulative'); // Total cumulative count from device
            $table->integer('incremental'); // Point-in-time incremental count
            $table->integer('duration')->nullable(); //duration conveyor off
            $table->enum('status', ['0', '1'])->default('0');
            
            $table->index(['line', 'created_at']);
            $table->index('created_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_dwp_time_alarm_counts');
    }
};
