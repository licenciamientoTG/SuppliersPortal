<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_line_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_monthly_distribution_id')->constrained('budget_monthly_distributions')->noActionOnDelete();
            $table->decimal('assigned_amount', 15, 2);
            $table->decimal('consumed_amount', 15, 2);
            $table->decimal('committed_amount', 15, 2);
            $table->date('captured_on');
            $table->string('source', 40)->default('distribution_change');
            $table->timestamps();
            $table->index(['budget_monthly_distribution_id', 'captured_on'], 'budget_line_history_lookup_idx');
        });

        Schema::create('budget_threshold_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_monthly_distribution_id')->constrained('budget_monthly_distributions')->noActionOnDelete();
            $table->unsignedTinyInteger('threshold_percent');
            $table->char('alert_month', 7);
            $table->decimal('usage_percent', 8, 2);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['budget_monthly_distribution_id', 'threshold_percent', 'alert_month'], 'budget_threshold_month_unique');
        });

        Schema::create('budget_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 50);
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('document_line_id');
            $table->foreignId('budget_monthly_distribution_id')->nullable()->constrained('budget_monthly_distributions')->noActionOnDelete();
            $table->foreignId('cost_center_id')->constrained()->noActionOnDelete();
            $table->foreignId('expense_category_id')->constrained()->noActionOnDelete();
            $table->foreignId('budget_cedula_id')->nullable()->constrained('budget_cedulas')->noActionOnDelete();
            $table->char('application_month', 7);
            $table->decimal('line_amount', 15, 2);
            $table->decimal('available_at_request', 15, 2);
            $table->decimal('requested_excess', 15, 2);
            $table->decimal('approved_excess', 15, 2)->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('PENDING');
            $table->foreignId('requested_by')->constrained('users')->noActionOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->text('decision_comment')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['document_type', 'document_id', 'document_line_id', 'status'], 'budget_exception_document_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_exceptions');
        Schema::dropIfExists('budget_threshold_alerts');
        Schema::dropIfExists('budget_line_history');
    }
};
