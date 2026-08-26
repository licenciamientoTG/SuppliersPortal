<?php

namespace App\Services\Rfq;

use App\Enum\RequisitionStatus;
use App\Exceptions\Rfq\IncompleteValidationException;
use App\Models\Requisition;
use App\Notifications\RequisitionInQuotationNotification;

/**
 * Firma de la validación técnica de Compras sobre una requisición
 * (los tres checks previos a cotizar). Compartido por el wizard y el tablero.
 */
class RequisitionValidationService
{
    /**
     * @param  array{specs_clear?: bool, time_feasible?: bool, alternatives_evaluated?: bool}  $checks
     *
     * @throws IncompleteValidationException
     */
    public function sign(Requisition $requisition, array $checks, ?string $notes, int $userId): void
    {
        if (
            ! ($checks['specs_clear'] ?? false) ||
            ! ($checks['time_feasible'] ?? false) ||
            ! ($checks['alternatives_evaluated'] ?? false)
        ) {
            throw new IncompleteValidationException('Debes completar todas las validaciones antes de continuar.');
        }

        $requisition->update([
            'status' => RequisitionStatus::IN_QUOTATION,
            'updated_by' => $userId,

            'pause_reason' => null,
            'paused_by' => null,
            'paused_at' => null,

            'validation_specs_clear' => true,
            'validation_time_feasible' => true,
            'validation_alternatives_evaluated' => true,
            'validated_at' => now(),
            'validated_by' => $userId,

            'purchasing_validation_notes' => $notes,
        ]);

        if ($requisition->requester) {
            app(\App\Services\SafeNotificationService::class)->notify(
                new RequisitionInQuotationNotification($requisition),
                [$requisition->requester],
                'de requisición en cotización',
                $requisition->folio,
                route('requisitions.show', $requisition),
            );
        }
    }
}
