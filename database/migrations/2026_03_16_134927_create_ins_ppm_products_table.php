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
        Schema::create('ins_ppm_products', function (Blueprint $table) {
            $table->id();
            $table->string('dev_style')->nullable();
            $table->string('product_code')->nullable();
            $table->string('color_way')->nullable();
            $table->date('production_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_ppm_products');
    }
};
