<?php

use App\Models\InsOmvCapture;
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
        Schema::table('ins_omv_captures', function (Blueprint $table) {
            $table->unsignedSmallInteger('taken_at')->nullable()->after('file_name');
        });

        $captures = InsOmvCapture::all();

        foreach ($captures as $capture) {
            $file_name = $capture->file_name;

            // Adjust regex to capture numbers with optional decimals
            preg_match('/(?:.*_){2}(\d+(?:\.\d+)?)_/', $file_name, $matches);
            $number = $matches[1] ?? null;

            if ($number !== null) {
                // Round to the nearest integer if a number is found
                $capture->taken_at = (int) round($number);
                $capture->save();
            } else {
                $capture->delete();
            }
        }

        // Make 'taken_at' not nullable after populating existing data
        Schema::table('ins_omv_captures', function (Blueprint $table) {
            $table->unsignedSmallInteger('taken_at')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_omv_captures', function (Blueprint $table) {
            $table->dropColumn('taken_at');
        });
    }
};
