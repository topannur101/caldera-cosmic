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
            $table->string('code')->nullable(); // should be indexed
            $table->tinyInteger('line'); // should be unsigned
            $table->enum('team', ['A', 'B', 'C'])->nullable();
            $table->unsignedBigInteger('user_1_id');
            $table->unsignedBigInteger('user_2_id')->nullable();
            $table->foreign('user_1_id')->references('id')->on('users');
            $table->foreign('user_2_id')->references('id')->on('users');
            $table->enum('eval', ['too_soon', 'on_time', 'too_late']);
            $table->dateTime('start_at'); // should be indexed
            $table->dateTime('end_at'); // should be indexed

            $table->index('ins_omv_recipe_id');
            $table->index('line');
            $table->index('team');
            $table->index('user_1_id');
            $table->index('user_2_id');
            $table->index('eval');
            
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
