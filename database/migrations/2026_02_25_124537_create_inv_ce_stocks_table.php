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
        Schema::create('inv_ce_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inv_ce_chemical_id')->constrained('inv_ce_chemicals');
            $table->integer('quantity')->default(0);
            $table->decimal('unit_size', 15, 2);
            $table->string('unit_uom');
            $table->decimal('lot_number', 15, 2);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->date('expiry_date');
            $table->json('planning_area');
            $table->enum('status', ['pending', 'approved', 'rejected', 'returned', 'expired']);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_ce_stock');
    }
};
