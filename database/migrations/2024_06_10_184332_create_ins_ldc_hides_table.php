a<?php

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
        Schema::create('ins_ldc_hides', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('code')->unique();
            $table->decimal('area_vn', 4, 2);
            $table->decimal('area_ab', 4, 2);
            $table->decimal('area_qt', 4, 2);
            $table->tinyInteger('grade')->nullable();
            $table->tinyInteger('shift');
            $table->foreignId('user_id');
            $table->foreignId('ins_ldc_group_id');

            $table->index('code');
            $table->index('grade');
            $table->index('shift');
            $table->index('user_id');
            $table->index('ins_ldc_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_ldc_hides');
    }
};
