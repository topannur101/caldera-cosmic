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
        Schema::create('inv_circs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('inv_item_id')->constrained()->cascadeOnDelete();
            $table->integer('qty');
            $table->enum('qtype', ['main', 'used', 'repaired']);
            $table->integer('qty_before');
            $table->integer('qty_after');
            $table->decimal('amount');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigner_id')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignId('evaluator_id')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired']);
            $table->string('remarks');
            $table->string('comment')->nullable();

            $table->index('inv_item_id');
            $table->index('user_id');
            $table->index('assigner_id');
            $table->index('evaluator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_circs');
    }
};
