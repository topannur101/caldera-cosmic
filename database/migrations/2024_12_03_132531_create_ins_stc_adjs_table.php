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
        if (Schema::hasTable('ins_stc_adjs')) {
            Schema::drop('ins_stc_adjs');
        }

        Schema::create('ins_stc_adjs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('user_id');
            $table->foreignId('ins_stc_machine_id');
            $table->enum('position', ['upper', 'lower']);
            $table->boolean('use_m_log_sv');
            
            $table->foreignId('ins_stc_d_sum_id');
            $table->foreignId('ins_stc_m_log_id')->nullable();
            $table->integer('formula_id');

            $table->tinyInteger('sv_p_1');
            $table->tinyInteger('sv_p_2');
            $table->tinyInteger('sv_p_3');
            $table->tinyInteger('sv_p_4');
            $table->tinyInteger('sv_p_5');
            $table->tinyInteger('sv_p_6');
            $table->tinyInteger('sv_p_7');
            $table->tinyInteger('sv_p_8');      
            
            $table->string('remarks')->nullable();
            $table->boolean('is_sent');

            $table->index('ins_stc_machine_id');
            $table->index(['ins_stc_machine_id', 'position']);
            $table->index('user_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_stc_adjs');
    }
};
