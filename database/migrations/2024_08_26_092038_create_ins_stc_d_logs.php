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
        Schema::create('ins_stc_d_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('ins_stc_d_sum_id');
            $table->timestamp('taken_at');
            $table->decimal('temp', 3, 1);
            
            $table->index('ins_stc_d_sum_id');
            $table->index('taken_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_stc_d_logs');
    }
};
