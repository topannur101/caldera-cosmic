<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ins_ctc_machines', function (Blueprint $table) {
            $table->id();
            $table->integer('line')->unique();           // Line number
            $table->string('ip_address')->unique();      // IP Address
            $table->timestamps();
            
            // Indexes for better performance
            $table->index('line');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ins_ctc_machines');
    }
};
