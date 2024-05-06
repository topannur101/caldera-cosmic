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
        Schema::create('kpi_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('kpi_area_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->smallInteger('year')->unsigned(); // max 32767
            $table->string('unit');
            $table->string('group')->nullable();
            $table->tinyInteger('order')->unsigned()->default(0); // max 100

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_items');
    }
};
