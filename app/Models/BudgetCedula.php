<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetCedula extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'budget_cedulas';

    protected $fillable = [
        'expense_category_id',
        'name',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'status'     => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ===== RELACIONES =====

    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function monthlyDistributions()
    {
        return $this->hasMany(BudgetMonthlyDistribution::class, 'budget_cedula_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // ===== SCOPES =====

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVO');
    }

    public function scopeNotDeleted($query)
    {
        return $query->whereNull('deleted_at');
    }
}
