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
        Schema::create('inv_order_budget', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->decimal('balance', 14, 2)->default(0);
            $table->foreignId('inv_curr_id')->constrained('inv_currs');
            $table->foreignId('inv_area_id')->constrained('inv_areas');
            $table->boolean('is_active')->default(true);
            
            $table->index('inv_curr_id');
            $table->index('inv_area_id');
        });

        Schema::create('inv_orders', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('user_id')->constrained();
            $table->string('order_number')->unique();
            $table->text('notes')->nullable();
            
            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::create('inv_order_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('inv_order_id')->nullable()->constrained('inv_orders');
            $table->foreignId('inv_item_id')->nullable()->constrained('inv_items')->nullOnDelete();
            $table->foreignId('inv_area_id')->constrained('inv_areas');
            $table->foreignId('inv_curr_id')->constrained('inv_currs');
            $table->foreignId('inv_order_budget_id')->nullable()->constrained('inv_order_budget');
            $table->string('name');
            $table->string('desc');
            $table->string('code');
            $table->string('photo')->nullable();
            $table->text('purpose');
            $table->unsignedInteger('qty');
            $table->string('uom');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('amount_budget', 14, 2)->default(0);
            $table->decimal('exchange_rate_used', 8, 2)->default(1.00);
            
            $table->index('inv_order_id');
            $table->index('inv_area_id');
            $table->index('inv_item_id');
            $table->index('inv_order_budget_id');
            $table->index(['inv_order_id', 'inv_area_id']);
        });

        Schema::create('inv_order_evals', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('inv_order_item_id')->constrained('inv_order_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->unsignedInteger('qty_before');
            $table->unsignedInteger('qty_after');
            $table->text('message')->nullable();
            $table->json('data')->nullable(); // should contain an array [ 'qty_increase', 'qty_decrease', 'budget_change', 'purpose_change' , 'item_info_change' ]
            
            $table->index('inv_order_item_id');
            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::create('inv_order_budget_snapshots', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('inv_order_id')->constrained('inv_orders')->cascadeOnDelete();
            $table->foreignId('inv_order_budget_id')->constrained('inv_order_budget');
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->foreignId('inv_curr_id')->constrained('inv_currs');
            
            $table->index('inv_order_id');
            $table->index('inv_order_budget_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_order_budget_snapshots');
        Schema::dropIfExists('inv_order_evals');
        Schema::dropIfExists('inv_order_items');
        Schema::dropIfExists('inv_orders');
        Schema::dropIfExists('inv_order_budget');
    }
};