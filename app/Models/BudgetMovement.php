<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'movement_type',
        'fiscal_year',
        'movement_date',
        'total_amount',
        'justification',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'fiscal_year' => 'integer',
    ];

    /**
     * Constantes para tipos de movimiento
     */
    const TYPE_TRANSFER = 'TRANSFERENCIA';

    const TYPE_INCREASE = 'AMPLIACION';

    const TYPE_DECREASE = 'REDUCCION';

    /**
     * Constantes para estados
     */
    const STATUS_PENDING = 'PENDIENTE';

    const STATUS_PENDING_ORIGIN = 'PENDIENTE_ORIGEN';

    const STATUS_PENDING_EXECUTIVE = 'PENDIENTE_DIRECCION';

    const STATUS_RETURNED = 'DEVUELTO';

    const STATUS_APPROVED = 'APROBADO';

    const STATUS_REJECTED = 'RECHAZADO';

    /**
     * Usuario que creó el movimiento
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Usuario que aprobó/rechazó el movimiento
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Detalles del movimiento
     */
    public function details(): HasMany
    {
        return $this->hasMany(BudgetMovementDetail::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(BudgetMovementDecision::class)->latest();
    }

    /**
     * Detalles de tipo ORIGEN (solo para transferencias)
     */
    public function originDetails(): HasMany
    {
        return $this->hasMany(BudgetMovementDetail::class)
            ->where('detail_type', BudgetMovementDetail::TYPE_ORIGIN);
    }

    /**
     * Detalles de tipo DESTINO (solo para transferencias)
     */
    public function destinationDetails(): HasMany
    {
        return $this->hasMany(BudgetMovementDetail::class)
            ->where('detail_type', BudgetMovementDetail::TYPE_DESTINATION);
    }

    /**
     * Detalles de tipo AJUSTE (para ampliaciones/reducciones)
     */
    public function adjustmentDetails(): HasMany
    {
        return $this->hasMany(BudgetMovementDetail::class)
            ->where('detail_type', BudgetMovementDetail::TYPE_ADJUSTMENT);
    }

    /**
     * Scope: Movimientos pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: Movimientos aprobados
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope: Movimientos rechazados
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope: Movimientos de un año fiscal específico
     */
    public function scopeForYear($query, $year)
    {
        return $query->where('fiscal_year', $year);
    }

    /**
     * Scope: Movimientos por tipo
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('movement_type', $type);
    }

    /**
     * Verificar si el movimiento es una transferencia
     */
    public function isTransfer(): bool
    {
        return $this->movement_type === self::TYPE_TRANSFER;
    }

    /**
     * Verificar si el movimiento es una ampliación
     */
    public function isIncrease(): bool
    {
        return $this->movement_type === self::TYPE_INCREASE;
    }

    /**
     * Verificar si el movimiento es una reducción
     */
    public function isDecrease(): bool
    {
        return $this->movement_type === self::TYPE_DECREASE;
    }

    /**
     * Verificar si el movimiento está pendiente
     */
    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PENDING_ORIGIN, self::STATUS_PENDING_EXECUTIVE], true);
    }

    public function isReturned(): bool
    {
        return $this->status === self::STATUS_RETURNED;
    }

    /**
     * Verificar si el movimiento está aprobado
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Verificar si el movimiento está rechazado
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
