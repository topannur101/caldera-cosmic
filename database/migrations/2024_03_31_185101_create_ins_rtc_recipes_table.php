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
        Schema::create('ins_rtc_recipes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('name')->unique();
            $table->string('og_rs');
            $table->decimal('thick_std_min', 4, 2);
            $table->decimal('thick_std_max', 4, 2);
            $table->decimal('thick_sl_min', 4, 2);
            $table->decimal('thick_sl_max', 4, 2);
            $table->decimal('scale', 5, 4);

            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_rtc_recipes');
    }
};
