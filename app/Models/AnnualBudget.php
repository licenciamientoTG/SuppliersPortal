<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AnnualBudget
 *
 * Presupuesto anual para un Centro de Costo.
 * Solo para centros con budget_type = ANNUAL
 */
class AnnualBudget extends Model
{
    use SoftDeletes;

    protected $table = 'annual_budgets';

    protected $fillable = [
        'cost_center_id',
        'fiscal_year',
        'total_annual_amount',
        'status',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'total_annual_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ===== SCOPES =====

    /**
     * Scope para cargar relaciones comunes y ordenamiento
     */
    public function scopeWithDetails($query)
    {
        return $query->with(['costCenter.company'])
            ->notDeleted()
            ->orderBy('fiscal_year', 'desc')
            ->orderBy('cost_center_id');
    }

    // ===== RELACIONES =====

    /**
     * Centro de costo (ANNUAL)
     */
    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * Distribuciones mensuales
     */
    public function monthlyDistributions()
    {
        return $this->hasMany(BudgetMonthlyDistribution::class);
    }

    /**
     * Usuario que aprobó
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Auditoría: creador
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Auditoría: modificador
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Auditoría: eliminador
     */
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // ===== SCOPES =====

    /**
     * Solo presupuestos aprobados
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'APROBADO');
    }

    /**
     * Solo presupuestos en planificación
     */
    public function scopePlanning($query)
    {
        return $query->where('status', 'PLANIFICACION');
    }

    /**
     * Para año fiscal específico
     */
    public function scopeForYear($query, $year)
    {
        return $query->where('fiscal_year', $year);
    }

    /**
     * No eliminados
     */
    public function scopeNotDeleted($query)
    {
        return $query->whereNull('deleted_at');
    }

    // ===== MÉTODOS =====

    /**
     * Obtener presupuesto disponible para mes y categoría
     */
    public function getAvailableForMonthAndCategory($month, $categoryId)
    {
        $distributions = $this->monthlyDistributions()
            ->where('month', $month)
            ->where('expense_category_id', $categoryId)
            ->get();

        if ($distributions->isEmpty()) {
            return 0;
        }

        return (float) $distributions->sum('assigned_amount')
            - (float) $distributions->sum('consumed_amount')
            - (float) $distributions->sum('committed_amount');
    }

    /**
     * Obtener etiqueta de estado
     */
    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'PLANIFICACION' => 'En Planificación',
            'APROBADO' => 'Aprobado',
            'CERRADO' => 'Cerrado',
            default => $this->status,
        };
    }

    /**
     * Verificar si puede ser modificado (solo si está en PLANIFICACION)
     */
    public function canBeModified(): bool
    {
        return $this->status === 'PLANIFICACION';
    }

    /**
     * Verificar si está aprobado
     */
    public function isApproved(): bool
    {
        return $this->status === 'APROBADO';
    }
}
