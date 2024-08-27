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
        Schema::create('ins_rdc_machines', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->tinyInteger('number')->unsigned();
            $table->string('name');
            $table->json('data');
        });

        $rpaElite =  [
            "Model" => "A12",
            "OGRS" => "D2",
            "Color" => "B12",
            "S'Max" => "G12",
            "S'Min" => "H12",
            "TC10" => "I12",
            "TC50" => "J12",
            "TC90" => "K12",
            "Status" => "L12"
        ];
        
        $mdrOne = [
            "Model" => "A12",
            "OGRS" => "D2",
            "Color" => "B12",
            "S'Max" => "J12",
            "S'Min" => "K12",
            "TC10" => "G12",
            "TC50" => "I12",
            "TC90" => "L12",
            "Status" => "M12"
        ];

        DB::table('ins_rdc_machines')->insert([
            [
                'ID' => 1,
                'NUMBER' => 2,
                'NAME' => 'RPA Elite',
                'DATA' => json_encode($rpaElite),
            ],
            [
                'ID' => 2,
                'NUMBER' => 3,
                'NAME' => 'RPA Elite',
                'DATA' => json_encode($rpaElite),
            ],
            [
                'ID' => 3,
                'NUMBER' => 4,
                'NAME' => 'MDR One',
                'DATA' => json_encode($mdrOne),
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_rdc_machines');
    }
};
