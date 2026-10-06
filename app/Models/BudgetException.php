<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetException extends Model
{
    protected $fillable = ['document_type', 'document_id', 'document_line_id', 'budget_monthly_distribution_id', 'cost_center_id', 'expense_category_id', 'budget_cedula_id', 'application_month', 'line_amount', 'available_at_request', 'requested_excess', 'approved_excess', 'reason', 'status', 'requested_by', 'decided_by', 'decision_comment', 'requested_at', 'decided_at', 'used_at'];

    protected $casts = ['line_amount' => 'decimal:2', 'available_at_request' => 'decimal:2', 'requested_excess' => 'decimal:2', 'approved_excess' => 'decimal:2', 'requested_at' => 'datetime', 'decided_at' => 'datetime', 'used_at' => 'datetime'];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function budgetCedula()
    {
        return $this->belongsTo(BudgetCedula::class);
    }
}
