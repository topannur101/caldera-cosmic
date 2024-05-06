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
        Schema::create('inv_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('name');
            $table->string('desc');
            $table->string('code')->nullable();
            $table->decimal('price')->nullable();
            $table->decimal('price_sec')->nullable();
            $table->foreignId('inv_curr_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inv_uom_id')->constrained()->restrictOnDelete();
            $table->foreignId('inv_loc_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inv_area_id')->constrained()->cascadeOnDelete();
            $table->integer('qty_main');
            $table->integer('qty_used');
            $table->integer('qty_rep');
            $table->decimal('freq');
            $table->integer('qty_main_min');
            $table->integer('qty_main_max');
            $table->integer('denom');
            $table->dateTime('loc_updated_at')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active');

            $table->index('code');
            $table->index('inv_curr_id');
            $table->index('inv_uom_id');
            $table->index('inv_loc_id');
            $table->index('inv_area_id');
            $table->unique(['code','inv_area_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_items');
    }
};
