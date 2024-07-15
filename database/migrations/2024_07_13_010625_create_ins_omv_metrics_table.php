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
        Schema::create('ins_omv_metrics', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('ins_omv_recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('user_1_id');
            $table->foreign('user_1_id')->references('id')->on('users');
            $table->unsignedBigInteger('user_2_id');
            $table->foreign('user_2_id')->references('id')->on('users');
            $table->enum('eval', ['early', 'on_time', 'late']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_omv_metrics');
    }
};
