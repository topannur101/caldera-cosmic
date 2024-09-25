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
        Schema::create('ins_rdc_stds', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('machine');
            $table->smallInteger('mcs'); // update: please also apply to tests table
            $table->foreignId('ins_rdc_tag_id')->nullable();
            $table->decimal('tc10', 5, 2);
            $table->decimal('tc90', 5, 2);

            $table->index('machine');
            $table->index('mcs');
            $table->index('ins_rdc_tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_rdc_stds');
    }
};
