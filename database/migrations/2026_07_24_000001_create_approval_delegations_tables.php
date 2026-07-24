<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegator_user_id')->constrained('users')->noActionOnDelete();
            $table->string('status', 20)->default('DRAFT');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('deactivated_by_user_id')->nullable()->constrained('users')->noActionOnDelete();
            $table->string('deactivation_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['delegator_user_id', 'status']);
            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('approval_delegation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_delegation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegate_user_id')->constrained('users')->noActionOnDelete();
            $table->timestamp('added_at');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index(['delegate_user_id', 'removed_at'], 'approval_delegation_members_delegate_active_idx');
            $table->index(['approval_delegation_id', 'removed_at'], 'approval_delegation_members_period_active_idx');
        });

        Schema::create('approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->foreignId('assigned_principal_user_id')->constrained('users')->noActionOnDelete();
            $table->foreignId('acted_by_user_id')->constrained('users')->noActionOnDelete();
            $table->foreignId('approval_delegation_id')->nullable()->constrained()->noActionOnDelete();
            $table->string('action', 30);
            $table->text('comments')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id']);
            $table->index(['assigned_principal_user_id', 'acted_at'], 'approval_decisions_principal_date_idx');
            $table->index(['acted_by_user_id', 'acted_at'], 'approval_decisions_actor_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
        Schema::dropIfExists('approval_delegation_members');
        Schema::dropIfExists('approval_delegations');
    }
};
