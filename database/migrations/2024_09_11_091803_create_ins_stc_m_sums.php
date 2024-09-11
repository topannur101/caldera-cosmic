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
        Schema::create('ins_stc_m_sums', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('ins_stc_machine_id')->constrained();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->decimal('s_1_temp', 3, 1);
            $table->decimal('s_2_temp', 3, 1);
            $table->decimal('s_3_temp', 3, 1);
            $table->decimal('s_4_temp', 3, 1);
            $table->decimal('s_5_temp', 3, 1);
            $table->decimal('s_6_temp', 3, 1);
            $table->decimal('s_7_temp', 3, 1);
            $table->decimal('s_8_temp', 3, 1);
            $table->decimal('median_speed', 3, 1);

            $table->index('ins_stc_machine_id');
            $table->index('start_time');
            $table->index('end_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_stc_m_sums');
    }
};
