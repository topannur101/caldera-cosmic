<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ins_ctc_recipes', function (Blueprint $table) {
            if (Schema::hasColumn('ins_ctc_recipes', 'component')) {
                $table->dropColumn('component');
            }
        });
    }

    public function down()
    {
        Schema::table('ins_ctc_recipes', function (Blueprint $table) {
            $table->string('component')->nullable();
        });
    }
};