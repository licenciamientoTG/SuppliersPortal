<?php

namespace App\Services\Rfq;

use App\Models\Rfq;
use App\Models\RfqBudgetBlockedNotice;
use App\Models\User;
use App\Notifications\RfqBudgetBlockedNotification;
use Illuminate\Support\Facades\DB;

class BudgetBlockedNoticeService
{
    public const DEFAULT_MESSAGE = 'No hay presupuesto disponible para continuar con esta adjudicación. Favor de revisarlo con el encargado de presupuesto.';

    public function __construct(private RfqAwardService $awards) {}

    public function isBlockedOnlyByBudget(Rfq $rfq, int $supplierId): bool
    {
        $diagnostics = $this->awards->supplierDiagnostics($rfq, $supplierId);

        if (! ($diagnostics['budget_blocked'] ?? false)) {
            return false;
        }

        $reasons = array_values(array_unique($diagnostics['reasons'] ?? []));
        $budgetMessages = array_values(array_unique($diagnostics['budget_messages'] ?? []));

        return $reasons !== [] && array_diff($reasons, $budgetMessages) === [];
    }

    public function send(Rfq $rfq, int $supplierId, User $buyer, ?string $note = null): RfqBudgetBlockedNotice
    {
        if (! $buyer->hasAnyRole(['buyer', 'superadmin'])) {
            throw new \DomainException('Sólo Compras o Superadministración puede informar el bloqueo presupuestal al requisitor.');
        }

        if ($rfq->trashed() || in_array($rfq->status, ['CANCELLED', 'REJECTED'], true)) {
            throw new \DomainException('No se puede informar presupuesto para una RFQ cancelada o rechazada.');
        }

        $rfq->loadMissing(['requisition.requester', 'requisition.items.costCenter', 'rfqResponses.requisitionItem']);

        if (! $rfq->requisition?->requester?->email) {
            throw new \DomainException('No se puede enviar el aviso porque el requisitor no tiene un correo válido.');
        }

        if (! $this->isBlockedOnlyByBudget($rfq, $supplierId)) {
            throw new \DomainException('Esta oferta no está bloqueada exclusivamente por presupuesto.');
        }

        $notice = DB::transaction(function () use ($rfq, $supplierId, $buyer, $note) {
            $lockedRfq = Rfq::query()->lockForUpdate()->findOrFail($rfq->id);

            if (RfqBudgetBlockedNotice::query()->where('rfq_id', $lockedRfq->id)->exists()) {
                throw new \DomainException('El requisitor ya fue informado sobre el presupuesto de este grupo.');
            }

            return RfqBudgetBlockedNotice::create([
                'rfq_id' => $lockedRfq->id,
                'requisition_id' => $lockedRfq->requisition_id,
                'supplier_id' => $supplierId,
                'buyer_user_id' => $buyer->id,
                'message' => self::DEFAULT_MESSAGE,
                'note' => filled($note) ? trim($note) : null,
                'notified_at' => now(),
            ]);
        });

        $notice->load(['rfq.quotationGroup', 'requisition.requester', 'supplier', 'buyer']);
        $notice->requisition->requester->notify(new RfqBudgetBlockedNotification($notice));

        return $notice;
    }
}
