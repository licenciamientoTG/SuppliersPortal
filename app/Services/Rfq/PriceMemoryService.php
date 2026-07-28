<?php

namespace App\Services\Rfq;

use App\Models\QuotationGroup;
use App\Models\RfqResponse;
use Illuminate\Support\Collection;

/**
 * Memoria de precios: última cotización conocida por producto, para
 * pre-llenar la captura manual del comprador y dar pistas por grupo.
 */
class PriceMemoryService
{
    /**
     * Última referencia de precio por partida, buscando el RfqResponse más
     * reciente del mismo producto (product_service_id) en cualquier
     * requisición. Solo respuestas reales (SUBMITTED/SELECTED, disponibles).
     *
     * @param  Collection  $items  colección de RequisitionItem
     * @return array<int, array{
     *     unit_price: float, iva_rate: float, currency: string,
     *     delivery_days: int|null, supplier_id: int, supplier_name: string,
     *     quotation_date: string|null, rfq_folio: string|null, response_id: int
     * }> indexado por requisition_item_id
     */
    public function latestForItems(Collection $items): array
    {
        $productIdByItem = $items
            ->filter(fn ($item) => ! empty($item->product_service_id))
            ->mapWithKeys(fn ($item) => [(int) $item->id => (int) $item->product_service_id]);

        if ($productIdByItem->isEmpty()) {
            return [];
        }

        $responses = RfqResponse::query()
            ->join('requisition_items', 'requisition_items.id', '=', 'rfq_responses.requisition_item_id')
            ->whereIn('requisition_items.product_service_id', $productIdByItem->values()->unique())
            ->whereIn('rfq_responses.status', ['SUBMITTED', 'SELECTED'])
            ->where('rfq_responses.not_available', false)
            ->with(['supplier:id,company_name', 'rfq:id,folio'])
            ->orderByDesc('rfq_responses.quotation_date')
            ->orderByDesc('rfq_responses.submitted_at')
            ->select('rfq_responses.*', 'requisition_items.product_service_id as memory_product_service_id')
            ->get();

        // Primera aparición por producto = la referencia más reciente
        $latestByProduct = [];
        foreach ($responses as $response) {
            $productId = (int) $response->memory_product_service_id;
            if (! isset($latestByProduct[$productId])) {
                $latestByProduct[$productId] = $response;
            }
        }

        $references = [];
        foreach ($productIdByItem as $itemId => $productId) {
            $response = $latestByProduct[$productId] ?? null;
            if (! $response) {
                continue;
            }

            $references[$itemId] = [
                'unit_price' => (float) $response->unit_price,
                'iva_rate' => (float) $response->iva_rate,
                'currency' => $response->currency ?? 'MXN',
                'delivery_days' => $response->delivery_days !== null ? (int) $response->delivery_days : null,
                'supplier_id' => (int) $response->supplier_id,
                'supplier_name' => $response->supplier?->company_name ?? 'Proveedor desconocido',
                'quotation_date' => $response->quotation_date?->format('Y-m-d'),
                'rfq_folio' => $response->rfq?->folio,
                'response_id' => (int) $response->id,
            ];
        }

        return $references;
    }

    /**
     * Pista para la tarjeta de un grupo: cuántas de sus partidas tienen una
     * referencia de precio reciente (menos de $freshDays días).
     *
     * @return array{with_recent: int, total: int, fresh_days: int}
     */
    public function groupHint(QuotationGroup $group, int $freshDays = 30): array
    {
        $items = $group->items()->get();
        $references = $this->latestForItems($items);

        $cutoff = now()->subDays($freshDays)->startOfDay();

        $withRecent = collect($references)
            ->filter(fn (array $ref) => $ref['quotation_date'] !== null
                && now()->parse($ref['quotation_date'])->greaterThanOrEqualTo($cutoff))
            ->count();

        return [
            'with_recent' => $withRecent,
            'total' => $items->count(),
            'fresh_days' => $freshDays,
        ];
    }
}
