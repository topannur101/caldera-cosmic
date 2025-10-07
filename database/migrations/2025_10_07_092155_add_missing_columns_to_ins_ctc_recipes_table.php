<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToInsCtcRecipesTable extends Migration
{
    public function up()
    {
        Schema::table('ins_ctc_recipes', function (Blueprint $table) {
            // Tambahkan kolom priority (integer)
            if (!Schema::hasColumn('ins_ctc_recipes', 'priority')) {
                $table->integer('priority')->default(1)->after('pfc_max');
            }

            // Tambahkan kolom is_active (boolean)
            if (!Schema::hasColumn('ins_ctc_recipes', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('priority');
            }

            // Tambahkan kolom recommended_for_models (JSON)
            if (!Schema::hasColumn('ins_ctc_recipes', 'recommended_for_models')) {
                $table->json('recommended_for_models')->nullable()->after('is_active');
            }
        });
    }

    public function down()
    {
        Schema::table('ins_ctc_recipes', function (Blueprint $table) {
            $table->dropColumn(['priority', 'is_active', 'recommended_for_models']);
        });
    }
}