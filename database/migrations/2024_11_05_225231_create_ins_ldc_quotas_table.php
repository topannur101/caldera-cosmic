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
        Schema::create('ins_ldc_quotas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('ins_ldc_group_id');
            $table->integer('machine');
            $table->decimal('value', 8, 2);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_ldc_quotas');
    }
};
