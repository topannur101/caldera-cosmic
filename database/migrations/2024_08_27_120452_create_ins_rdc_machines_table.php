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
            $table->json('cells');
        });

        Schema::table('ins_rdc_tests', function (Blueprint $table) {
            $table->foreignId('ins_rdc_machine_id')->constrained();
        });

        $rpaElite = [
            ["field" => "model", "address" => "A12"],
            ["field" => "mcs", "address" => "D2"],
            ["field" => "color", "address" => "B12"],
            ["field" => "s_max", "address" => "G12"],
            ["field" => "s_min", "address" => "H12"],
            ["field" => "tc10", "address" => "I12"],
            ["field" => "tc50", "address" => "J12"],
            ["field" => "tc90", "address" => "K12"],
            ["field" => "eval", "address" => "L12"],
            ["field" => "code_alt", "address" => "E12"]
        ];
        
        $mdrOne = [
            ["field" => "model", "address" => "A12"],
            ["field" => "mcs", "address" => "D2"],
            ["field" => "color", "address" => "B12"],
            ["field" => "s_max", "address" => "J12"],
            ["field" => "s_min", "address" => "K12"],
            ["field" => "tc10", "address" => "G12"],
            ["field" => "tc50", "address" => "I12"],
            ["field" => "tc90", "address" => "L12"],
            ["field" => "eval", "address" => "M12"],
            ["field" => "code_alt", "address" => "E12"]
        ];

        DB::table('ins_rdc_machines')->insert([
            [
                'ID' => 1,
                'NUMBER' => 2,
                'NAME' => 'RPA ELITE',
                'CELLS' => json_encode($rpaElite),
            ],
            [
                'ID' => 2,
                'NUMBER' => 3,
                'NAME' => 'RPA ELITE',
                'CELLS' => json_encode($rpaElite),
            ],
            [
                'ID' => 3,
                'NUMBER' => 4,
                'NAME' => 'MDR ONE',
                'CELLS' => json_encode($mdrOne),
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_rdc_tests', function (Blueprint $table) {
            $table->dropForeign(['ins_rdc_machine_id']);
            $table->dropColumn('ins_rdc_machine_id');        
        });
        Schema::dropIfExists('ins_rdc_machines');
    }
};
