<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

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

    public function requisitionItems()
    {
        return $this->hasMany(RequisitionItem::class, 'budget_cedula_id');
    }

    public function budgetCommitments()
    {
        return $this->hasMany(BudgetCommitment::class, 'budget_cedula_id');
    }

    /**
     * Productos/servicios del catálogo clasificados con esta cédula
     */
    public function productServices()
    {
        return $this->belongsToMany(ProductService::class);
    }

    public function subaccount()
    {
        return $this->hasOne(Subaccount::class, 'legacy_budget_cedula_id');
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

    // ===== MÉTODOS: VALIDACIONES =====

    public function isInactive(): bool
    {
        return $this->status === 'INACTIVO';
    }

    /**
     * Verificar si la cédula está en uso real: distribuciones, partidas de
     * requisición o compromisos presupuestales que la referencien.
     */
    public function isInUse(): bool
    {
        return $this->monthlyDistributions()->exists()
            || $this->requisitionItems()->exists()
            || $this->budgetCommitments()->exists()
            || $this->productServices()->exists();
    }

    public function getDeactivationErrorMessage(): ?string
    {
        return 'No se puede desactivar/eliminar una cédula que tiene movimientos presupuestales activos (distribuciones, requisiciones o compromisos).';
    }

    // ===== VALIDACIONES =====

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!in_array($model->status, ['ACTIVO', 'INACTIVO'])) {
                $model->status = 'ACTIVO';
            }

            if (!$model->created_by) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('status') && $model->status === 'INACTIVO') {
                if ($model->isInUse()) {
                    throw new \Exception($model->getDeactivationErrorMessage());
                }
            }

            if (!$model->updated_by) {
                $model->updated_by = Auth::id();
            }
        });

        static::deleting(function ($model) {
            $model->deleted_by = Auth::id();
            $model->save();
        });
    }
}
