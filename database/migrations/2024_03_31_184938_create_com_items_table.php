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

            $table->string('mod')->nullable();
            $table->unsignedBigInteger('mod_id')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('com_items')->onDelete('cascade');
            $table->text('content')->nullable();

            $table->index('mod');
            $table->index('mod_id');
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
