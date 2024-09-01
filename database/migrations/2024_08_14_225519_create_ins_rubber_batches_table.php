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
        Schema::create('ins_rubber_batches', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('code')->unique();
            $table->string('model')->nullable();
            $table->string('color')->nullable();
            $table->string('mcs')->nullable();
            $table->enum('rdc_eval', ['queue', 'pass', 'fail'])->nullable();
            $table->enum('omv_eval', ['too_soon', 'on_time', 'too_late'])->nullable();
            $table->string('code_alt')->nullable();

            $table->index('code');
            $table->index('rdc_eval');
            $table->index('omv_eval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_rubber_batches');
    }
};
