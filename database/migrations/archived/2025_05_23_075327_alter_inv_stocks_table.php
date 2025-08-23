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
            $table->decimal('wf', 5, 2)->default(0); // withdrawal frequency

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inv_stocks', function (Blueprint $table) {
            $table->dropColumn('wf');
        });
    }
};
