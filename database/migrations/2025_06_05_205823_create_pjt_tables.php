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
        // Create pjt_teams table first (no dependencies)
        Schema::create('pjt_teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('short_name', 10);
            $table->timestamps();

            // Indexes
            $table->index('name');
            $table->unique('short_name');
        });

        // Create pjt_items table (depends on pjt_teams and users)
        Schema::create('pjt_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('desc', 256);
            $table->foreignId('pjt_team_id')->constrained('pjt_teams')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('location', 100)->nullable();
            $table->string('photo')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            // Indexes
            $table->index('name');
            $table->index('status');
            $table->index('pjt_team_id');
            $table->index('user_id');
            $table->index(['status', 'pjt_team_id']);
        });

        // Create pjt_members table (depends on pjt_items and users)
        Schema::create('pjt_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pjt_item_id')->constrained('pjt_items')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Indexes
            $table->index('pjt_item_id');
            $table->index('user_id');
            
            // Composite unique constraint - one membership per user per project
            $table->unique(['pjt_item_id', 'user_id']);
        });

        // Create pjt_tasks table (depends on pjt_items and users)
        Schema::create('pjt_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->text('desc');
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('pjt_item_id')->constrained('pjt_items')->onDelete('cascade');
            $table->foreignId('assignee_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('hour_work')->default(0);
            $table->integer('hour_remaining')->default(0);
            $table->enum('category', [
                'breakdown_repair',
                'project_improvement', 
                'report',
                'tpm',
                'meeting',
                'other'
            ])->default('other');
            $table->timestamps();

            // Indexes
            $table->index('name');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('pjt_item_id');
            $table->index('assignee_id');
            $table->index('assigner_id');
            $table->index('category');
            $table->index(['pjt_item_id', 'assignee_id']);
            $table->index(['start_date', 'end_date']);
            $table->index(['category', 'pjt_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order of creation (due to foreign key constraints)
        Schema::dropIfExists('pjt_tasks');
        Schema::dropIfExists('pjt_members');
        Schema::dropIfExists('pjt_items');
        Schema::dropIfExists('pjt_teams');
    }
};