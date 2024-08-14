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
        Schema::create('ins_rdc_tests', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('ins_rubber_batch_id');
            $table->enum('eval', ['pass', 'fail']);
            $table->enum('machine', ['mdr', 'ta_rpa']);
            $table->decimal('s_min', 4, 2);
            $table->decimal('s_max', 4, 2);
            $table->decimal('tc10', 5, 2);
            $table->decimal('tc50', 5, 2);
            $table->decimal('tc90', 5, 2);
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_rdc_tests');
    }
};
