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
        Schema::table('ins_rdc_tests', function (Blueprint $table) {

            $table->dropColumn(['tag', 'tc10_min', 'tc10_max', 'tc90_min', 'tc90_max']);
            $table->decimal('tc50', 5, 2)->nullable()->change();

            $table->decimal('s_min_low', 4, 2)->default(0);
            $table->decimal('s_min_high', 4, 2)->default(0);
            $table->decimal('s_max_low', 4, 2)->default(0);
            $table->decimal('s_max_high', 4, 2)->default(0);
            $table->decimal('tc10_low', 5, 2)->default(0);
            $table->decimal('tc10_high', 5, 2)->default(0);
            $table->decimal('tc50_low', 5, 2)->nullable();
            $table->decimal('tc50_high', 5, 2)->nullable();
            $table->decimal('tc90_low', 5, 2)->default(0);
            $table->decimal('tc90_high', 5, 2)->default(0);
            
            $table->enum('type', ['-', 'SLOW', 'FAST'])->default('-');

            $table->index('type');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ins_rdc_tests', function (Blueprint $table) {
            // Add dropped columns back
            $table->string('tag')->nullable();
            $table->decimal('tc10_min', 5, 2)->nullable();
            $table->decimal('tc10_max', 5, 2)->nullable();
            $table->decimal('tc90_min', 5, 2)->nullable();
            $table->decimal('tc90_max', 5, 2)->nullable();
    
            // Revert changes to 'tc50' column
            $table->decimal('tc50', 5, 2)->nullable(false)->change();
    
            // Drop newly added columns
            $table->dropColumn([
                's_min_low', 's_min_high', 's_max_low', 's_max_high',
                'tc10_low', 'tc10_high', 'tc50_low', 'tc50_high',
                'tc90_low', 'tc90_high'
            ]);
    
            // Drop the added 'type' column and its index
            $table->dropIndex(['type']); // Drop the index first
            $table->dropColumn('type');
        });
    }
};
