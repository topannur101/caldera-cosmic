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
        Schema::create('ins_rtc_drecipes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('name');
            $table->string('og_rs');
            $table->decimal('std_min', 4, 2);
            $table->decimal('std_max', 4, 2);
            $table->decimal('std_mid', 4, 2);
            $table->decimal('scale', 4, 2);
            $table->decimal('pfc_min', 4, 2);
            $table->decimal('pfc_max', 4, 2);

            $table->index('name');
            $table->index('og_rs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_rtc_drecipes');
    }
};
