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
        Schema::create('com_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('model_name');
            $table->unsignedBigInteger('model_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('com_items')->onDelete('cascade');
            $table->text('content')->nullable();
            $table->string('url');

            $table->index('model_name');
            $table->index('model_id');
            $table->index('user_id');
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('com_items');
    }
};
