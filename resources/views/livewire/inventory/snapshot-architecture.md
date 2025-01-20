Snapshot architecture

<?php

// currencies
Schema::create('inv_currs', function (Blueprint $table) {
    $table->id();
    $table->timestamps();

    $table->string('name')->unique();
    $table->decimal('rate', 10, 2);
});

DB::table('inv_currs')->insert([
   [
      'name' => 'USD',
      'rate' => 1
   ]
]);

// units of measurement
Schema::create('inv_uoms', function (Blueprint $table) {
    $table->id();
    $table->timestamps();

    $table->string('name')->unique();
});

// inventory areas
Schema::create('inv_areas', function (Blueprint $table) {
    $table->id();
    $table->timestamps();

    $table->string('name')->unique();
});

DB::table('inv_areas')->insert([
   [
         'name' => 'TT MM',
   ]
]);

// inventory locations
Schema::create('inv_locs', function (Blueprint $table) {
    $table->id();
    $table->timestamps();

    $table->string('name');
    $table->foreignId('inv_area_id')->constrained();

   $table->unique(['name','inv_area_id']);
   $table->index('inv_area_id');

});

// inventory items
Schema::create('inv_items', function (Blueprint $table) {
    $table->id();
    $table->timestamps();

    $table->string('name');
    $table->string('desc');
    $table->string('code')->nullable();
    $table->foreignId('inv_loc_id')->nullable()->constrained();
    $table->foreignId('inv_area_id')->constrained();
    $table->string('photo');
    $table->boolean('is_active');

   $table->index('code');
   $table->index('inv_loc_id');
   $table->index('inv_area_id');
   $table->unique(['code','inv_area_id']);
});

// inventory tags
Schema::create('inv_tags', function (Blueprint $table) {
    $table->id();
    $table->timestamps();

    $table->string('name');
    $table->foreignId('inv_area_id')->constrained();

   $table->unique(['name','inv_area_id']);
   $table->index('inv_area_id');
});

// inventory item tags (bridge table)
Schema::create('inv_item_tags', function (Blueprint $table) {
    $table->id();
    $table->timestamps();
    
    $table->foreignId('inv_item_id')->constrained();
    $table->foreignId('inv_tag_id')->constrained();

   $table->unique(['inv_item_id','inv_tag_id']);
   $table->index('inv_item_id');
   $table->index('inv_tag_id');

});

/// check up to here

// inventory item UoMs (with pricing)
Schema::create('inv_item_uoms', function (Blueprint $table) {
    $table->id();
    $table->enum('type', ['main', 'repaired', 'used'])->default('main');
    $table->foreignId('inv_item_id')->constrained();
    $table->foreignId('inv_uom_id')->constrained();
    $table->foreignId('inv_curr_id')->default(1)->constrained();
    $table->decimal('price', 15, 2)->default(0);
    $table->boolean('is_base')->default(false);
    $table->timestamps();

    // Ensure unique combination of item and UoM. eg. Pen EA USD
    $table->unique(['inv_item_id', 'inv_uom_id', 'inv_curr_id']);
});

<!-- // inventory UoM conversions
Schema::create('inv_item_uoms_conversions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('inv_item_id')->constrained();
    $table->foreignId('from_id')->constrained('inv_item_uoms');
    $table->foreignId('to_id')->constrained('inv_item_uoms');
    $table->decimal('qty_factor', 15, 4);
    $table->decimal('price_factor', 15, 4);
    $table->timestamps();

    // Ensure unique combination of item and conversion pair
    $table->unique(['inv_item_id', 'from_uom', 'to_uom']);
}); -->

// inventory circulation (transactions)
Schema::create('inv_circs', function (Blueprint $table) {
    $table->id();
    $table->enum('type', ['in', 'out', 'capture']);
    $table->enum('evaluation_status');
    $table->foreignId('evaluator_id');
    $table->string('evaluation_remarks');
    $table->foreignId('inv_item_uom_id')->constrained('inv_item_uoms');
    $table->decimal('qty', 15, 2);
    $table->decimal('amount', 15, 2);
    $table->decimal('unit_price', 15, 2);  // Added for historical price tracking
    $table->string('remarks')->nullable();
    $table->timestamps();
});

// inventory authorization
Schema::create('inv_auths', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users');
    $table->foreignId('inv_area_id')->constrained('inv_areas');
    $table->json('actions');
    $table->timestamps();
});

// inventory current stocks (running balance)
Schema::create('inv_current_stocks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('inv_item_uom_id')->unique()->constrained('inv_item_uoms');
    $table->decimal('qty', 15, 2)->default(0);
    $table->timestamp('last_updated');
    $table->timestamps();
});

// inventory stock snapshots
Schema::create('inv_stock_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('inv_item_uom_id')->constrained('inv_item_uoms');
    $table->decimal('qty', 15, 2);
    $table->timestamp('snapshot_date');
    $table->timestamps();

    // Index for efficient querying of latest snapshots
    $table->index(['inv_item_uom_id', 'snapshot_date']);
});

// Create indexes for better query performance
Schema::table('inv_circs', function (Blueprint $table) {
    $table->index(['inv_item_uom_id', 'created_at']);
});

Schema::table('inv_current_stocks', function (Blueprint $table) {
    $table->index(['inv_item_uom_id', 'last_updated']);
});