<?php

namespace App\Enum;

/**
 * Estados de una Requisición
 * PASO 3B - Enum actualizado con estado PAUSED
 *
 * INSTRUCCIÓN: Reemplazar el contenido completo de app/Enum/RequisitionStatus.php con este código
 */
enum RequisitionStatus: string
{
    case DRAFT = 'DRAFT'; // Borrador. No enviado a Compras.
    case PENDING = 'PENDING'; // Enviada a Compras. Pendiente de validación.
    case PAUSED = 'PAUSED'; // Esperando aprobación de producto en catálogo.
    case APPROVED = 'APPROVED'; // Aprobada por el encargado de compras.
    case REJECTED = 'REJECTED'; // Rechazada por el encargado de compras.
    case IN_QUOTATION = 'IN_QUOTATION'; // En proceso de cotización.
    case QUOTED = 'QUOTED'; // Cotizada, pendiente de decisión de adjudicación.
    case IN_APPROVAL = 'IN_APPROVAL'; // Adjudicación pendiente de autorización.
    case PENDING_BUDGET_ADJUSTMENT = 'PENDING_BUDGET_ADJUSTMENT'; // Pendiente de ajuste presupuestal.
    case COMPLETED = 'COMPLETED'; // Completada. Se ha creado el cotización.
    case CANCELLED = 'CANCELLED'; // Cancelada. Se ha cancelado la requisición.

    /**
     * Obtiene las opciones para selects
     */
    public static function options(): array
    {
        return [
            self::DRAFT->value => 'Borrador', // No enviado a Compras.
            self::PENDING->value => 'Pendiente de Validación',
            self::PAUSED->value => 'Pausada (Esperando Catálogo)',
            self::APPROVED->value => 'Aprobada',
            self::REJECTED->value => 'Rechazada',
            self::IN_QUOTATION->value => 'En Cotización',
            self::QUOTED->value => 'Cotizada',
            self::IN_APPROVAL->value => 'En aprobación',
            self::PENDING_BUDGET_ADJUSTMENT->value => 'Pendiente Ajuste Presupuestal',
            self::COMPLETED->value => 'Completada',
            self::CANCELLED->value => 'Cancelada',
        ];
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::PENDING => 'warning',
            self::PAUSED => 'info',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::IN_QUOTATION => 'info',
            self::QUOTED => 'primary',
            self::IN_APPROVAL => 'warning',
            self::PENDING_BUDGET_ADJUSTMENT => 'warning',
            self::COMPLETED => 'success',
            self::CANCELLED => 'dark',
        };
    }

    /**
     * Obtiene el icono de Tabler según el estado
     */
    public static function icon(self $status): string
    {
        return match ($status) {
            self::DRAFT => 'file-draft',
            self::PENDING => 'clock-hour-4',
            self::PAUSED => 'player-pause',
            self::APPROVED => 'circle-check',
            self::REJECTED => 'circle-x',
            self::IN_QUOTATION => 'file-invoice',
            self::QUOTED => 'receipt-2',
            self::IN_APPROVAL => 'hourglass-high',
            self::PENDING_BUDGET_ADJUSTMENT => 'file-dollar',
            self::COMPLETED => 'circle-check-filled',
            self::CANCELLED => 'x',
        };
    }

    /**
     * Determina si el estado permite edición
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::DRAFT, self::PAUSED, self::PENDING_BUDGET_ADJUSTMENT => true,
            default => false,
        };
    }

    /**
     * Determina si el estado permite agregar/quitar ítems
     */
    public function canModifyItems(): bool
    {
        return match ($this) {
            self::DRAFT, self::PAUSED, self::PENDING_BUDGET_ADJUSTMENT => true,
            default => false,
        };
    }

    /**
     * Determina si el estado permite cancelación
     */
    public function isCancellable(): bool
    {
        return match ($this) {
            self::DRAFT, self::PENDING, self::PAUSED, self::IN_QUOTATION, self::QUOTED, self::IN_APPROVAL, self::PENDING_BUDGET_ADJUSTMENT => true,
            default => false,
        };
    }

    /**
     * Transiciones permitidas desde este estado
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::PENDING],
            self::PENDING => [self::APPROVED, self::REJECTED, self::PAUSED, self::IN_QUOTATION],
            self::PAUSED => [self::PENDING, self::CANCELLED],
            self::IN_QUOTATION => [self::QUOTED, self::CANCELLED],
            self::QUOTED => [self::IN_APPROVAL, self::APPROVED, self::REJECTED, self::PENDING_BUDGET_ADJUSTMENT],
            self::IN_APPROVAL => [self::APPROVED, self::COMPLETED, self::REJECTED, self::IN_QUOTATION, self::PENDING_BUDGET_ADJUSTMENT, self::CANCELLED],
            self::PENDING_BUDGET_ADJUSTMENT => [self::QUOTED, self::CANCELLED],
            self::APPROVED => [self::COMPLETED, self::CANCELLED],
            self::REJECTED => [self::PENDING],
            self::COMPLETED => [],
            self::CANCELLED => [],
        };
    }

    /**
     * Verifica si se puede transicionar al estado dado
     */
    public function canTransitionTo(self $targetStatus): bool
    {
        return in_array($targetStatus, $this->allowedTransitions());
    }

    /**
     * Obtiene el label para mostrar en la UI
     */
    public function label(): string
    {
        return self::options()[$this->value];
    }
}
