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
            $table->smallInteger('speed')->unsigned();

            $table->decimal('pv_1', 3, 1);
            $table->decimal('pv_2', 3, 1);
            $table->decimal('pv_3', 3, 1);
            $table->decimal('pv_4', 3, 1);
            $table->decimal('pv_5', 3, 1);
            $table->decimal('pv_6', 3, 1);
            $table->decimal('pv_7', 3, 1);
            $table->decimal('pv_8', 3, 1);
            $table->decimal('sv_1', 3, 1);
            $table->decimal('sv_2', 3, 1);
            $table->decimal('sv_3', 3, 1);
            $table->decimal('sv_4', 3, 1);
            $table->decimal('sv_5', 3, 1);
            $table->decimal('sv_6', 3, 1);
            $table->decimal('sv_7', 3, 1);
            $table->decimal('sv_8', 3, 1);

            $table->index('ins_stc_machine_id');
            $table->index('position');
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
