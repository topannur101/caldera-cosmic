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
        Schema::create('ins_stc_devices', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('code');
            $table->string('name');

            $table->index('code');
        });

        DB::table('ins_stc_devices')->insert([
            [
                'ID' => 1,
                'CODE' => 'TEST-DEVICE-001',
                'NAME' => 'TEST DEVICE',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_stc_devices');
    }
};
