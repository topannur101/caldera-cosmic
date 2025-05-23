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
        Schema::create('ins_ctc_metrics', function (Blueprint $table) {

            $table->id();
            $table->timestamps();

            $table->foreignId('ins_ctc_machine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ins_rubber_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ins_ctc_recipe_id')->nullable()->constrained();
            $table->boolean('is_auto')->default(0);
            
            // Left side thickness metrics
            $table->decimal('t_mae_left', 4, 2)->nullable(); // Mean Average Error left
            $table->decimal('t_ssd_left', 4, 2)->nullable();  // Sample Standard Deviation left
            $table->decimal('t_avg_left', 4, 2)->nullable(); // Average thickness left
            
            // Right side thickness metrics
            $table->decimal('t_mae_right', 4, 2)->nullable(); // Mean Average Error right
            $table->decimal('t_ssd_right', 4, 2)->nullable();  // Sample Standard Deviation right
            $table->decimal('t_avg_right', 4, 2)->nullable(); // Average thickness right
            
            // Balance metric between left and right
            $table->decimal('t_balance', 4, 2)->nullable(); // Thickness balance (t_avg_left - t_avg_right)
            
            // Combined metrics (average of left and right)
            $table->decimal('t_mae', 4, 2)->nullable(); // Mean Average Error (average of left and right)
            $table->decimal('t_ssd', 4, 2)->nullable();  // Sample Standard Deviation (average of left and right)
            $table->decimal('t_avg', 4, 2)->nullable(); // Average thickness (average of left and right)
            
            // Detailed measurement data
            $table->json('data'); // timestamp (date time), is_correcting (boolean), 
                                 // action_left (boolean), action_right (boolean), 
                                 // left (decimal), right (decimal)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ins_ctc_metrics');
    }
};
