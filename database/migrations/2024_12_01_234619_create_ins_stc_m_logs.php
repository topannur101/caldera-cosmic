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
        if (Schema::hasTable('ins_stc_m_logs')) {
            Schema::drop('ins_stc_m_logs');
        }

        if (Schema::hasTable('ins_stc_m_sums')) {
            Schema::drop('ins_stc_m_sums');
        }

        Schema::create('ins_stc_m_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('ins_stc_machine_id')->constrained();
            $table->enum('position', ['upper', 'lower']);

            $table->tinyInteger('pv_r_1');
            $table->tinyInteger('pv_r_2');
            $table->tinyInteger('pv_r_3');
            $table->tinyInteger('pv_r_4');
            $table->tinyInteger('pv_r_5');
            $table->tinyInteger('pv_r_6');
            $table->tinyInteger('pv_r_7');
            $table->tinyInteger('pv_r_8');

            // php modbus akan kirim ke sini
            $table->tinyInteger('sv_p_1');
            $table->tinyInteger('sv_p_2');
            $table->tinyInteger('sv_p_3');
            $table->tinyInteger('sv_p_4');
            $table->tinyInteger('sv_p_5');
            $table->tinyInteger('sv_p_6');
            $table->tinyInteger('sv_p_7');
            $table->tinyInteger('sv_p_8');

            // value masuk ke sini jika sudah tekan koreksi
            $table->tinyInteger('sv_w_1');
            $table->tinyInteger('sv_w_2');
            $table->tinyInteger('sv_w_3');
            $table->tinyInteger('sv_w_4');
            $table->tinyInteger('sv_w_5');
            $table->tinyInteger('sv_w_6');
            $table->tinyInteger('sv_w_7');
            $table->tinyInteger('sv_w_8');

            // value masuk ke sini jika mode override (boolean) true
            $table->tinyInteger('sv_r_1');
            $table->tinyInteger('sv_r_2');
            $table->tinyInteger('sv_r_3');
            $table->tinyInteger('sv_r_4');
            $table->tinyInteger('sv_r_5');
            $table->tinyInteger('sv_r_6');
            $table->tinyInteger('sv_r_7');
            $table->tinyInteger('sv_r_8');

            $table->index('ins_stc_machine_id');
            $table->index(['ins_stc_machine_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_stc_m_logs');
    }
};
