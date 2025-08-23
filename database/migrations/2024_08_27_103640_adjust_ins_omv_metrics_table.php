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
        Schema::table('ins_omv_metrics', function (Blueprint $table) {
            $table->foreignId('ins_rubber_batch_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_omv_metrics', function (Blueprint $table) {
            $table->dropForeign(['ins_rubber_batch_id']);
            $table->dropColumn('ins_rubber_batch_id');
        });
    }
};
