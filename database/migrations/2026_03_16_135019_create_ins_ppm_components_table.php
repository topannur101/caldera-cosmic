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
        Schema::create('ins_ppm_components', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id');
            $table->string('part_name')->nullable();
            $table->string('base_part_name')->nullable();
            $table->string('description')->nullable();
            $table->string('material_number')->nullable();
            $table->string('mcs_number')->nullable();
            $table->string('vendor_type')->nullable();
            $table->string('hera_hardness')->nullable();
            $table->json('size_distribution')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_ppm_components');
    }
};
