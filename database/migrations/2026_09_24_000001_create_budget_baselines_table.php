<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annual_budget_id')->constrained('annual_budgets')->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->foreignId('expense_category_id')->constrained('expense_categories')->noActionOnDelete();
            $table->foreignId('budget_cedula_id')->nullable()->constrained('budget_cedulas')->noActionOnDelete();
            $table->decimal('original_amount', 15, 2);
            $table->string('source', 20);
            $table->timestamp('captured_at');
            $table->foreignId('captured_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamps();
            $table->index(['annual_budget_id', 'month'], 'idx_budget_baselines_budget_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_baselines');
    }
};
