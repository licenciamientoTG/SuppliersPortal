<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use Illuminate\Support\Facades\Auth;

class ContractPurchaseOrderService
{
    /**
     * Genera una PurchaseOrder por cada proveedor distinto en la requisición.
     * Requiere que todos los items tengan contract_id y unit_price snapshot.
     */
    public function generateFromRequisition(Requisition $requisition): void
    {
        $itemsBySupplier = $requisition->items
            ->load(['contract.supplier'])
            ->groupBy(fn (RequisitionItem $item) => $item->contract->supplier_id);

        foreach ($itemsBySupplier as $supplierId => $items) {
            $first = $items->first();

            $subtotal = $items->sum(fn ($i) => $i->unit_price * $i->quantity);
            $iva      = round($subtotal * 0.16, 2);
            $total    = round($subtotal + $iva, 2);

            $po = PurchaseOrder::create([
                'folio'                 => $this->nextPoFolio(),
                'requisition_id'        => $requisition->id,
                'supplier_id'           => $supplierId,
                'quotation_summary_id'  => null,       // OC por contrato, sin cotización
                'source_type'           => 'contract',
                'receiving_location_id' => $requisition->receiving_location_id,
                'subtotal'              => $subtotal,
                'iva_amount'            => $iva,
                'total'                 => $total,
                'currency'              => $first->currency_code ?? 'MXN',
                'status'                => 'OPEN',
                'created_by'            => Auth::id(),
            ]);

            foreach ($items as $item) {
                $po->items()->create([
                    'requisition_item_id' => $item->id,
                    'description'         => $item->description,
                    'quantity'            => $item->quantity,
                    'unit_price'          => $item->unit_price,
                    'subtotal'            => round($item->unit_price * $item->quantity, 2),
                    'iva_amount'          => round($item->unit_price * $item->quantity * 0.16, 2),
                    'total'               => round($item->unit_price * $item->quantity * 1.16, 2),
                ]);
            }
        }
    }

    private function nextPoFolio(): string
    {
        $year   = date('Y');
        $prefix = "OC-{$year}-";
        $last   = PurchaseOrder::where('folio', 'like', $prefix . '%')
            ->orderBy('folio', 'desc')
            ->value('folio');

        $n = 0;
        if ($last && preg_match('/OC-\d{4}-(\d+)/', $last, $m)) {
            $n = (int) $m[1];
        }

        return sprintf('%s%04d', $prefix, $n + 1);
    }
}
