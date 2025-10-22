<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
    // Jika kolom sudah ada, jangan lakukan apa-apa
    if (! Schema::hasColumn('ins_ctc_machines', 'is_active')) {
        Schema::table('ins_ctc_machines', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('ip_address');
            });
        }
    }

    public function down()
    {
        Schema::table('ins_ctc_machines', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};