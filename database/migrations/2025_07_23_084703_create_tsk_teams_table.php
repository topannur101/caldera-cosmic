<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tsk_teams', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->string('short_name', 10);
            $table->string('desc')->nullable();
            $table->boolean('is_active')->default(true);
            $table->index('short_name');
        });

        // Insert demo team
        DB::table('tsk_teams')->insert([
            'name' => 'Digitalization',
            'short_name' => 'DGT',
            'desc' => 'Digital transformation and technology initiatives',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tsk_teams');
    }
};