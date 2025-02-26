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

        Schema::create('inv_currs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->char('name', 3)->unique();
            $table->decimal('rate', 8, 2);
            $table->boolean('is_active')->default(true);
        });

        DB::table('inv_currs')->insert([
            [
                'name' => 'USD',
                'rate' => 1.00,
            ],
            [
                'name' => 'IDR',
                'rate' => 16290.00,
            ]
        ]);
        
        Schema::create('inv_areas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name')->unique();
        });

        DB::table('inv_areas')->insert([
            [
                'name' => 'DEMO AREA'
            ]
        ]);
        

        Schema::create('inv_auths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('inv_area_id')->constrained('inv_areas');
            $table->json('actions');
            $table->timestamps();
        });

        Schema::create('inv_locs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('parent');
            $table->string('bin');
        });

        Schema::create('inv_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->string('desc');
            $table->string('code')->nullable();
            $table->foreignId('inv_loc_id')->nullable()->constrained();
            $table->foreignId('inv_area_id')->constrained();
            $table->string('photo')->nullable();
            $table->boolean('is_active');
            $table->index('code');
            $table->index('inv_loc_id');
            $table->index('inv_area_id');
        });

        Schema::create('inv_stocks', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('inv_item_id')->constrained();
            $table->foreignId('inv_curr_id')->constrained();
            $table->unsignedInteger('qty')->default(0);
            $table->string('uom'); // ea, pcs
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->index('inv_item_id');
            $table->index('inv_curr_id');
        });

        Schema::create('inv_tags', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
        });

        Schema::create('inv_item_tags', function (Blueprint $table) {
            $table->timestamps();
            $table->foreignId('inv_item_id')->constrained();
            $table->foreignId('inv_tag_id')->constrained();
            $table->primary(['inv_item_id', 'inv_tag_id']);            
            $table->index('inv_item_id');
            $table->index('inv_tag_id');
        });

        Schema::create('inv_circs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('user_id')->constrained();
            $table->enum('type', ['deposit', 'withdrawal', 'capture']);
            $table->enum('eval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('eval_user_id')->nullable()->constrained('users');
            $table->string('eval_remarks')->nullable();
            $table->foreignId('inv_stock_id')->constrained();
            $table->unsignedInteger('qty_relative')->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('unit_price', 14, 2);  // Added for historical price tracking
            $table->string('remarks')->nullable();
            $table->boolean('is_delegated')->default(false);
            $table->index('eval_user_id');
            $table->index('inv_stock_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'inv_circs',
            'inv_item_tags',
            'inv_tags',
            'inv_stocks',
            'inv_items',
            'inv_locs',
            'inv_auths',
            'inv_areas',
            'inv_currs',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
