<?php

namespace App\Services;

use App\Models\QuotationSummary;
use App\Models\QuotationSummaryItem;
use App\Models\RfqResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationSummaryItemService
{
    public function syncFromSelectedSupplier(QuotationSummary $summary): Collection
    {
        $summary->loadMissing('rfq.rfqResponses.requisitionItem');

        $responses = $summary->rfq->rfqResponses
            ->where('supplier_id', $summary->selected_supplier_id)
            ->where('status', 'SUBMITTED')
            ->where('not_available', false)
            ->values();

        foreach ($responses as $response) {
            $this->createOrUpdateFromResponse($summary, $response);
        }

        return $summary->items()->with('requisitionItem')->get();
    }

    public function ensureItems(QuotationSummary $summary): Collection
    {
        if (! $summary->items()->exists()) {
            return $this->syncFromSelectedSupplier($summary);
        }

        return $summary->items()->with('requisitionItem')->get();
    }

    public function applyApproval(QuotationSummary $summary, array $itemPayload): Collection
    {
        return DB::transaction(function () use ($summary, $itemPayload) {
            $items = $this->ensureItems($summary)->keyBy('id');
            $payload = collect($itemPayload)->keyBy(fn (array $item) => (int) ($item['id'] ?? 0));

            if ($payload->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Debes capturar cantidades aprobadas para las partidas.',
                ]);
            }

            foreach ($items as $item) {
                $linePayload = $payload->get($item->id);

                if (! $linePayload) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.approved_quantity" => 'Falta la cantidad aprobada de una partida.',
                    ]);
                }

                $approvedQuantity = (float) ($linePayload['approved_quantity'] ?? 0);
                $notes = trim((string) ($linePayload['approver_notes'] ?? ''));
                $quotedQuantity = (float) $item->quoted_quantity;

                if ($approvedQuantity < 0 || $approvedQuantity > $quotedQuantity + 0.000001) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.approved_quantity" => 'La cantidad aprobada debe estar entre 0 y la cantidad cotizada.',
                    ]);
                }

                if ($approvedQuantity + 0.000001 < $quotedQuantity && $notes === '') {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.approver_notes" => 'Captura un motivo para toda reduccion de cantidad.',
                    ]);
                }

                $item->approved_quantity = $approvedQuantity;
                $item->approver_notes = $notes !== '' ? $notes : null;
                $item->recalculate();
                $item->save();
            }

            return $summary->items()->with('requisitionItem')->get();
        });
    }

    private function createOrUpdateFromResponse(QuotationSummary $summary, RfqResponse $response): QuotationSummaryItem
    {
        $quantity = (float) $response->quantity;
        $item = QuotationSummaryItem::firstOrNew([
            'quotation_summary_id' => $summary->id,
            'rfq_response_id' => $response->id,
        ]);

        if (! $item->exists) {
            $item->approved_quantity = $quantity;
            $item->approver_notes = null;
        }

        $item->fill([
            'requisition_item_id' => $response->requisition_item_id,
            'quoted_quantity' => $quantity,
            'unit_price' => (float) $response->unit_price,
            'iva_rate' => (float) ($response->iva_rate ?? 16),
        ]);

        $item->recalculate();
        $item->approval_status = $item->exists ? $item->approval_status : 'pending';
        $item->save();

        return $item;
    }
}
