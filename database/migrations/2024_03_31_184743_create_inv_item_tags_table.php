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
        Schema::create('inv_item_tags', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('inv_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inv_tag_id')->constrained()->cascadeOnDelete();

            $table->unique(['inv_item_id','inv_tag_id']);
            $table->index('inv_item_id');
            $table->index('inv_tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_item_tags');
    }
};
