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
        Schema::create('kpi_scores', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('user_id')->constrained();
            $table->foreignId('kpi_item_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('month')->unsigned(); // max 255
            $table->decimal('target')->nullable();
            $table->decimal('actual')->nullable();
            $table->boolean('is_submitted')->default(0);

            $table->unique(['kpi_item_id','month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_scores');
    }
};
