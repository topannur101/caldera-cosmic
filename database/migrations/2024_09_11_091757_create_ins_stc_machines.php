<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ins_stc_machines', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('code');
            $table->string('name');
            $table->tinyInteger('line')->unsigned();
            $table->ipAddress('ip_address')->nullable();

            $table->index('code');
            $table->index('line');
        });

        DB::table('ins_stc_machines')->insert([
            [
                'ID' => 1,
                'CODE' => 'TEST-MACHINE-001',
                'NAME' => 'TEST MACHINE',
                'LINE' => 99,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_stc_machines');
    }
};
