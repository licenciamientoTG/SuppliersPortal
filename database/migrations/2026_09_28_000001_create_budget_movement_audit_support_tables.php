<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_distribution_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annual_budget_id')->constrained('annual_budgets')->noActionOnDelete();
            $table->foreignId('budget_monthly_distribution_id')->constrained('budget_monthly_distributions')->noActionOnDelete();
            $table->unsignedTinyInteger('month');
            $table->foreignId('expense_category_id')->constrained('expense_categories')->noActionOnDelete();
            $table->foreignId('budget_cedula_id')->nullable()->constrained('budget_cedulas')->noActionOnDelete();
            $table->decimal('original_amount', 15, 2);
            $table->string('source', 30)->default('ANNUAL_BUDGET_APPROVAL');
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->unique('budget_monthly_distribution_id', 'ux_budget_baseline_distribution');
            $table->index(['annual_budget_id', 'month']);
        });

        Schema::create('budget_movement_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_movement_id')->constrained('budget_movements')->noActionOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('budget_movement_id');
        });

        Schema::table('budget_movements', function (Blueprint $table) {
            $table->foreignId('reversal_of_id')->nullable()->after('approved_at')
                ->constrained('budget_movements')->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_of_id');
        });
        Schema::dropIfExists('budget_movement_attachments');
        Schema::dropIfExists('budget_distribution_baselines');
    }
};
