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
        Schema::table('inv_stocks', function (Blueprint $table) {
            $table->unsignedInteger('qty_min')->default(0);
            $table->unsignedInteger('qty_max')->default(0);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inv_stocks', function (Blueprint $table) {
            $table->dropColumn('qty_min');
            $table->dropColumn('qty_max');

        });
    }
};
