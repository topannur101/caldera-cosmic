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
        Schema::create('ldc_hides', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('code')->unique();
            $table->decimal('areaVn', 4, 2);
            $table->decimal('areaAb', 4, 2);
            $table->decimal('areaQt', 4, 2);
            $table->tinyInteger('grade')->nullable();
            $table->date('workdate');
            $table->string('style');
            $table->string('line');
            $table->tinyInteger('shift');
            $table->string('material');
            $table->foreignId('user_id');

            $table->index('code');
            $table->index('grade');
            $table->index('workdate');
            $table->index('style');
            $table->index('line');
            $table->index('shift');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ldc_hides');
    }
};
