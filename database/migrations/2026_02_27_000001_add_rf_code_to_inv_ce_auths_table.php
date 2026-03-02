<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inv_ce_auths', function (Blueprint $table) {
            if (! Schema::hasColumn('inv_ce_auths', 'rf_code')) {
                $table->string('rf_code')->unique()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inv_ce_auths', function (Blueprint $table) {
            if (Schema::hasColumn('inv_ce_auths', 'rf_code')) {
                $table->dropUnique(['rf_code']);
                $table->dropColumn('rf_code');
            }
        });
    }
};

