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
        Schema::create('ins_ldc_groups', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->date('workdate');
            $table->string('style');
            $table->string('line');
            $table->string('material')->nullable();

            $table->index('workdate');
            $table->index('style');
            $table->index('line');
            $table->index('material');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_ldc_groups');
    }
};
