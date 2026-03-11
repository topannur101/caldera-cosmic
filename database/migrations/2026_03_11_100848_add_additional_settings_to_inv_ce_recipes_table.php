<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('inv_ce_recipes', 'additional_settings')) {
            Schema::table('inv_ce_recipes', function (Blueprint $table) {
                $table->json('additional_settings')->nullable()->after('potlife')->comment('Extra settings, e.g. {"up_dev":0.5,"low_dev":0.5,"target_weight":3}');
            });
        }
    }

    public function down(): void
    {
        Schema::table('inv_ce_recipes', function (Blueprint $table) {
            $table->dropColumn('additional_settings');
        });
    }
};
