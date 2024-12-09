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
        if (Schema::hasTable('ins_stc_d_sums')) {
            if (Schema::hasColumn('ins_stc_d_sums', 'set_temps')) {
                Schema::table('ins_stc_d_sums', function (Blueprint $table) {
                    $table->renameColumn('set_temps', 'sv_temps');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
