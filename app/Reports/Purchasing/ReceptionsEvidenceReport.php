<?php

namespace App\Reports\Purchasing;

use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Reception;
use App\Models\ReceptionAttachment;
use App\Models\ReceptionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * RR-01 · Recepciones y su evidencia. Una fila por evento de recepción (OC/OCD), con el
 * detalle de sus renglones y de sus archivos adjuntos (remisión + fotos) con SHA-256.
 *
 * Limitaciones de negocio documentadas (ver docs/reportes/RR-01_mapeo_y_huecos.md §2.2):
 * - No existe un segundo rol de "validación": validated_by/validated_at son siempre iguales
 *   a received_by/received_at (recibir y validar son el mismo acto).
 * - No existe el tipo HITO; reception_type solo distingue BIEN/SERVICIO.
 */
class ReceptionsEvidenceReport
{
    /** Roles que ven todo el universo de sus empresas asignadas (o todo si no tienen). */
    public const FULL_SCOPE_ROLES = ['superadmin', 'general_director', 'accounting', 'buyer'];

    /** Empresas que el usuario puede consultar; null = sin restricción (ve todas). */
    public function visibleCompanyIds(User $user): ?array
    {
        if ($user->hasRole('superadmin')) {
            return null;
        }

        if ($user->hasRole(self::FULL_SCOPE_ROLES)) {
            $ids = $user->companies()->pluck('companies.id')->all();

            return empty($ids) ? null : $ids;
        }

        return [];
    }

    /**
     * @param  array{company_ids: int[], location_ids: int[], reception_type: string[], received_by: ?int, date_from: ?string, date_to: ?string, evidence_filter: string}  $params
     * @return array{rows: Collection, kpis: array}
     */
    public function build(User $user, array $params): array
    {
        $visibleCompanyIds = $this->visibleCompanyIds($user);

        $rows = $this->rows()
            ->filter(fn (array $row) => $visibleCompanyIds === null || in_array($row['company_id'], $visibleCompanyIds, true))
            ->filter(fn (array $row) => $this->matches($row, $params))
            ->sortByDesc('reception_date')
            ->values();

        return [
            'rows' => $rows,
            'kpis' => $this->kpis($rows),
        ];
    }

    private function rows(): Collection
    {
        return Reception::query()
            ->with([
                'receivable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    PurchaseOrder::class => ['supplier', 'requisition'],
                    DirectPurchaseOrder::class => ['supplier'],
                ]),
                'receivingLocation',
                'receiver',
                'items',
                'attachments.uploader',
            ])
            ->get()
            ->filter(fn (Reception $reception) => $reception->receivable !== null)
            ->map(fn (Reception $reception) => $this->row($reception));
    }

    private function row(Reception $reception): array
    {
        $order = $reception->receivable;
        $isDirect = $order instanceof DirectPurchaseOrder;

        $this->loadItemCategories($reception->items);

        $items = $reception->items->map(fn (ReceptionItem $item) => [
            'po_line' => $item->receivable_item_id,
            'description' => $this->itemDescription($item),
            'quantity_received' => (float) $item->quantity_received,
            'conformity' => $item->conformity,
        ])->values();

        $attachments = $reception->attachments->map(fn (ReceptionAttachment $attachment) => [
            'id' => $attachment->id,
            'attachable_type' => $attachment->attachable_type,
            'file_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->size,
            'sha256' => $attachment->sha256,
            'hash_origin' => $attachment->hash_origin,
            'uploaded_at' => $attachment->uploaded_at?->toIso8601String(),
            'uploaded_by_name' => $attachment->uploader?->name,
        ])->values();

        $receivedAt = $reception->received_at ? Carbon::parse($reception->received_at) : null;

        return [
            'reception_id' => $reception->id,
            'reception_folio' => $reception->folio,
            'order_type' => $isDirect ? 'OCD' : 'OC',
            'po_folio' => $order->folio ?? 'DRAFT',
            'order_url' => $isDirect
                ? route('direct-purchase-orders.show', $order->id)
                : route('purchase-orders.show', $order->id),
            'company_id' => $reception->receivingLocation?->company_id,
            'location_id' => $reception->receivingLocation?->id,
            'location_name' => $reception->receivingLocation?->name,
            'supplier_name' => $order->supplier?->company_name,
            'reception_type' => $this->receptionType($reception->items),
            'reception_date' => $receivedAt?->toIso8601String(),
            'received_by_id' => $reception->received_by,
            'received_by_name' => $reception->receiver?->name,
            'validated_by_name' => $reception->receiver?->name,
            'validated_at' => $receivedAt?->toIso8601String(),
            'sod_flag' => $this->sodFlag($reception, $order),
            'evidence_count' => $this->evidenceCount($reception),
            'evidence_complete' => $this->evidenceComplete($reception),
            'items' => $items,
            'attachments' => $attachments,
        ];
    }

    /**
     * Precarga la cadena de categoría de gasto de cada renglón recibido, según el tipo real
     * del ítem referenciado (OC vía su requisition_item, OCD de forma directa).
     */
    private function loadItemCategories(Collection $items): void
    {
        $items->loadMorph('receivableItem', [
            PurchaseOrderItem::class => ['requisitionItem.expenseCategory'],
            DirectPurchaseOrderItem::class => ['expenseCategory'],
        ]);
    }

    private function itemDescription(ReceptionItem $item): ?string
    {
        $receivableItem = $item->receivableItem;

        return $receivableItem?->description ?? null;
    }

    /**
     * BIEN/SERVICIO derivado de las partidas recibidas (ver §2.1 del plan): si alguna partida
     * pertenece a una categoría de gasto de servicio, la recepción se clasifica como SERVICIO.
     * No existe el tipo HITO en el sistema (fuera de alcance, no se simula).
     */
    private function receptionType(Collection $items): string
    {
        $hasService = $items->contains(function (ReceptionItem $item) {
            $receivableItem = $item->receivableItem;

            if ($receivableItem instanceof DirectPurchaseOrderItem) {
                return $receivableItem->expenseCategory?->isService() ?? false;
            }

            if ($receivableItem instanceof PurchaseOrderItem) {
                return $receivableItem->requisitionItem?->expenseCategory?->isService() ?? false;
            }

            return false;
        });

        return $hasService ? 'SERVICIO' : 'BIEN';
    }

    /**
     * Señal real de segregación de funciones (ver §2.2 del plan): quien recibió es el mismo
     * que solicitó la requisición de la OC, o quien creó la OCD.
     */
    private function sodFlag(Reception $reception, PurchaseOrder|DirectPurchaseOrder $order): bool
    {
        $requesterId = $order instanceof DirectPurchaseOrder
            ? $order->created_by
            : $order->requisition?->requested_by;

        return $requesterId !== null && (int) $requesterId === (int) $reception->received_by;
    }

    /**
     * Cuenta la evidencia real (reception_attachments) cuando ya existe; si la recepción es
     * anterior al backfill (`receptions:backfill-attachment-hashes`), cae a las columnas
     * heredadas (remission_path + photos) para no subcontar evidencia que sí existe en disco.
     */
    private function evidenceCount(Reception $reception): int
    {
        if ($reception->attachments->isNotEmpty()) {
            return $reception->attachments->count();
        }

        $legacyPhotos = $reception->items->sum(fn (ReceptionItem $item) => count((array) $item->photos));

        return ($reception->remission_path ? 1 : 0) + $legacyPhotos;
    }

    /**
     * Regla única vigente (decisión del usuario, §2.3): evidencia completa = archivo de
     * remisión presente. No hay catálogo de evidencia exigida por categoría.
     */
    private function evidenceComplete(Reception $reception): bool
    {
        if ($reception->remission_path !== null) {
            return true;
        }

        return $reception->attachments->contains(fn (ReceptionAttachment $attachment) => $attachment->isRemission());
    }

    private function matches(array $row, array $params): bool
    {
        if (! empty($params['company_ids']) && ! in_array($row['company_id'], $params['company_ids'], true)) {
            return false;
        }
        if (! empty($params['location_ids']) && ! in_array($row['location_id'], $params['location_ids'], true)) {
            return false;
        }
        if (! empty($params['reception_type']) && ! in_array($row['reception_type'], $params['reception_type'], true)) {
            return false;
        }
        if (! empty($params['received_by']) && $row['received_by_id'] !== $params['received_by']) {
            return false;
        }
        if (! empty($params['date_from']) && (! $row['reception_date'] || Carbon::parse($row['reception_date'])->lt(Carbon::parse($params['date_from'])->startOfDay()))) {
            return false;
        }
        if (! empty($params['date_to']) && (! $row['reception_date'] || Carbon::parse($row['reception_date'])->gt(Carbon::parse($params['date_to'])->endOfDay()))) {
            return false;
        }

        $evidenceFilter = $params['evidence_filter'] ?? 'TODAS';
        if ($evidenceFilter === 'COMPLETA' && ! $row['evidence_complete']) {
            return false;
        }
        if ($evidenceFilter === 'INCOMPLETA' && $row['evidence_complete']) {
            return false;
        }

        return true;
    }

    private function kpis(Collection $rows): array
    {
        $complete = $rows->where('evidence_complete', true);
        $sodConflicts = $rows->where('sod_flag', true);

        return [
            'total_receptions' => $rows->count(),
            'evidence_complete_count' => $complete->count(),
            'evidence_complete_pct' => $rows->isEmpty() ? 0.0 : round($complete->count() / $rows->count() * 100, 2),
            'sod_conflicts_count' => $sodConflicts->count(),
        ];
    }
}
