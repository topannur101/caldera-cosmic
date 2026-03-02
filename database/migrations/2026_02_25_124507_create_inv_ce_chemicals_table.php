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
        Schema::create('inv_ce_chemicals', function (Blueprint $table) {
            $table->id();
            $table->string('item_code');
            $table->string('name');
            $table->foreignId('inv_ce_vendor_id')->constrained('inv_ce_vendors');
            $table->string('uom');
            $table->enum('category_chemical', ['single', 'double']);
            $table->string('photo')->nullable();
            $table->integer('location_id')->nullable();
            $table->integer('area_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_ce_chemicals');
    }
};
