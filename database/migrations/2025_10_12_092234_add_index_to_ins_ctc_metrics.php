<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ins_ctc_metrics', function (Blueprint $table) {
            // Index untuk query: where device + latest created_at
            $table->index(['ins_ctc_machine_id', 'created_at'], 'idx_ctc_machine_created');
        });
    }

    public function down()
    {
        Schema::table('ins_ctc_metrics', function (Blueprint $table) {
            $table->dropIndex('idx_ctc_machine_created');
        });
    }
};