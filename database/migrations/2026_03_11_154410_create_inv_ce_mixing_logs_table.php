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
        Schema::create('inv_ce_mixing_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipe_id')->index()->comment('Reference to inv_ce_recipes');
            $table->unsignedBigInteger('user_id')->index()->comment('Reference to users');
            $table->string('batch_number')->unique();
            $table->time('duration')->nullable()->comment('Time taken for the mixing process');
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending')->comment('Status of the mixing process');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_ce_mixing_logs');
    }
};
