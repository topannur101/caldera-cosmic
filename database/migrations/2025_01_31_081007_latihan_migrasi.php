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
        // Imam
        Schema::create("mesin", function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer("nomor");
            $table->string("serial");
            $table->string("type");

        });

        Schema::create("material", function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->enum('category', ['leather', 'synthetic', 'mesh']); // sintetik, kulit
            $table->string('nama'); // HULEX EDD, POLYPAG
            $table->string('style'); // DD900-1200
            $table->string('komponen'); // FOXING, TIP 
        });

        // Lia
        Schema::create('karyawan', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('nama'); // Lia
            $table->foreignId('tim_id');
            $table->string('NIK');
        });

        Schema::create('tim', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('nama'); // LCA, DGT, DEP
        });

        // Bintang
        Schema::create('log', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('kerusakan'); // kabel putus, terbakar
            $table->string('item_code'); // TBE_10-
            $table->boolean('is_rusak'); // ya atau tidak
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
