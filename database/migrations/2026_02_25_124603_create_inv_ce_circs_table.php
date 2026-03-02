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
        Schema::create('inv_ce_circs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inv_ce_stock_id')->constrained('inv_ce_stock');
            $table->foreignId('inv_ce_auth_id')->constrained('inv_ce_auths');
            $table->string('actual_area');
            $table->decimal('issued_quantity', 15, 2);
            $table->enum('type_circ', ['issued', 'returned']);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_ce_circs');
    }
};
