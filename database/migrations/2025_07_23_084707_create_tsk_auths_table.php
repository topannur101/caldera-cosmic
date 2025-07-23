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
        Schema::create('tsk_auths', function (Blueprint $table) {
            $table->timestamps();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('tsk_team_id')->constrained();
            $table->json('perms'); // JSON field for permissions like inventory
            $table->enum('role', ['leader', 'member'])->default('member');
            $table->boolean('is_active')->default(true);
            $table->primary(['user_id', 'tsk_team_id']);
            $table->index('user_id');
            $table->index('tsk_team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tsk_auths');
    }
};