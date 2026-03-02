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
        Schema::create('inv_ce_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inv_ce_circ_id')->constrained('inv_ce_circs');
            $table->foreignId('inv_ce_auth_id')->constrained('inv_ce_auths');
            $table->foreignId('inv_ce_stock_id')->constrained('inv_ce_stock');
            $table->decimal('returned_quantity', 15, 2);
            $table->enum('type_return', ['returned', 'expired']);
            $table->enum('condition', ['good', 'damaged', 'contaminated']);
            $table->string('remarks')->nullable();
            $table->date('original_expiry_date');
            $table->date('restock_expiry_date');
            $table->boolean('is_restocked')->default(false);
            $table->timestamp('restocked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_ce_returns');
    }
};
