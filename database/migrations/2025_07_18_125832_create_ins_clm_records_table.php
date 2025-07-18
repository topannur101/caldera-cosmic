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
        Schema::create('ins_clm_records', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->enum('location', ['ip'])->default('ip');
            $table->decimal('temperature', 4, 1);
            $table->decimal('humidity', 4, 1);

            $table->index('location');
            $table->index('created_at');
            $table->index(['location', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_clm_records');
    }
};
