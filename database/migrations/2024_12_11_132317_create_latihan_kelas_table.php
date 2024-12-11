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
        Schema::create('latihan_kelas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            // protected $fillable = [
            //     'nama',         // text contoh: VII-G
            //     'lantai',       // nomor: 1
            // ];

            $table->string('nama');
            $table->integer('lantai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('latihan_kelas');
    }
};
