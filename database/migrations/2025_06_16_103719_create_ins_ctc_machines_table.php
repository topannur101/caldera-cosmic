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
        Schema::create('ins_ctc_machines', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->integer('line')->unsigned()->unique();
            $table->ipAddress('ip_address');

            $table->index('line');
            $table->unique(['line', 'ip_address']);
            
        });

                // Seed the recipe data (converted from legacy data)
        DB::table('ins_ctc_machines')->insert([
            [
                'line' => 3,
                'ip_address' => '172.70.86.50'
            ],
            [
                'line' => 4,
                'ip_address' => '172.70.89.149'
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_ctc_machines');
    }
};
