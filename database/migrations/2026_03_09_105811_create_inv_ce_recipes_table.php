<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_ce_recipes', function (Blueprint $table) {
            $table->id();

            // Identity / classification
            $table->string('line', 50)->comment('Line code, e.g. E1, E2');
            $table->string('model', 255)->comment('Model / product name, e.g. Vomero');
            $table->string('area', 255)->comment('Work area, e.g. Assy');

            // Input chemicals
            $table->unsignedBigInteger('chemical_id')->comment('Base chemical (Component A), FK → inv_ce_chemicals');
            $table->unsignedBigInteger('hardener_id')->comment('Hardener (Component B), FK → inv_ce_chemicals');

            // Mixing ratio
            $table->decimal('hardener_ratio', 8, 4)->default(0)->comment('Hardener percentage/ratio, e.g. 5 for 5%');

            // Output
            $table->string('output_code', 255)->comment('Resulting mix code, e.g. 111 GN ** As');

            // Process config
            $table->decimal('potlife', 8, 2)->default(0)->comment('Pot life duration (hours)');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('chemical_id')
                ->references('id')->on('inv_ce_chemicals')
                ->restrictOnDelete();

            $table->foreign('hardener_id')
                ->references('id')->on('inv_ce_chemicals')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_ce_recipes');
    }
};
