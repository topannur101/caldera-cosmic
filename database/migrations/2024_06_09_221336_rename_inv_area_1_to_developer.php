<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('inv_areas')
        ->where('id', 1)
        ->update(['name' => 'DEVELOPER']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inv_areas', function (Blueprint $table) {
            //
        });
    }
};
