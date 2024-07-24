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
        Schema::create('ins_omv_recipes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            
            $table->enum('type', ['new', 'remixing', 'scrap']);
            $table->string('name')->unique();
            $table->json('steps');
            $table->json('capture_points')->nullable();

            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_omv_recipes');
    }
};
