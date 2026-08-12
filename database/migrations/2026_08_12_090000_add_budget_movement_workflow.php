<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_movements', function (Blueprint $table) {
            $table->string('status', 30)->default('PENDIENTE_DIRECCION')->change();
        });

        Schema::create('budget_movement_approval_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('director_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('substitute_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('substitute_starts_at')->nullable();
            $table->timestamp('substitute_ends_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('budget_movement_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_movement_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 20);
            $table->string('action', 20);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comments')->nullable();
            $table->timestamps();
            $table->index(['budget_movement_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_movement_decisions');
        Schema::dropIfExists('budget_movement_approval_settings');
    }
};
