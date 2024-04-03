<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ins_rtc_devices', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->integer('line')->unique();
            $table->ipAddress('ip_address');

            $table->index('line');
            $table->unique(['line','ip_address']);
        });

        DB::table('ins_rtc_devices')->insert([
            [
                'id'            => 1,
                'line'          => 3,
                'ip_address'    => '172.70.86.12',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_rtc_devices');
    }
};
