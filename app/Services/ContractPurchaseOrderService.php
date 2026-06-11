<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\DirectPurchaseOrder;
use App\Models\Requisition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContractPurchaseOrderService
{
    /**
     * Genera una OCD por proveedor a partir de una requisición por contrato.
     *
     * @throws \RuntimeException si alguna partida no tiene cost_center_id o expense_category_id
     */
    public function generateFromRequisition(Requisition $requisition): Collection
    {
        $items = $requisition->items()->with('contract.supplier', 'productService')->get();

        foreach ($items as $item) {
            if (! $item->cost_center_id) {
                throw new \RuntimeException(
                    "La partida \"{$item->description}\" no tiene Centro de Costo asignado."
                );
            }
            if (! $item->expense_category_id) {
                throw new \RuntimeException(
                    "La partida \"{$item->description}\" no tiene Categoría de Gasto asignada."
                );
            }
        }

        $bySupplier = $items->groupBy(fn ($item) => $item->contract?->supplier_id)
            ->filter(fn ($group, $sid) => $sid !== null);

        $ocds = collect();

        foreach ($bySupplier as $supplierId => $group) {
            $ocd = DB::transaction(function () use ($supplierId, $group, $requisition) {
                $currency  = $group->first()->currency_code ?? 'MXN';
                $subtotal  = $group->sum(fn ($i) => round((float) $i->unit_price * (float) $i->quantity, 4));
                $ivaAmount = round($subtotal * 0.16, 2);
                $total     = round($subtotal + $ivaAmount, 2);
                $subtotal  = round($subtotal, 2);

                $ocd = DirectPurchaseOrder::create([
                    'supplier_id'           => $supplierId,
                    'receiving_location_id' => $requisition->receiving_location_id,
                    'application_month'     => now()->format('Y-m'),
                    'justification'         => "Requisición por contrato {$requisition->folio}",
                    'subtotal'              => $subtotal,
                    'iva_amount'            => $ivaAmount,
                    'total'                 => $total,
                    'currency'              => $currency,
                    'status'                => 'DRAFT',
                    'created_by'            => Auth::id(),
                ]);

                foreach ($group as $item) {
                    $itemSubtotal = round((float) $item->unit_price * (float) $item->quantity, 2);
                    $itemIva      = round($itemSubtotal * 0.16, 2);

                    $ocd->items()->create([
                        'expense_category_id' => $item->expense_category_id,
                        'cost_center_id'      => $item->cost_center_id,
                        'description'         => $item->description,
                        'quantity'            => $item->quantity,
                        'unit_price'          => $item->unit_price,
                        'iva_rate'            => 16.00,
                        'subtotal'            => $itemSubtotal,
                        'iva_amount'          => $itemIva,
                        'total'               => $itemSubtotal + $itemIva,
                        'unit_of_measure'     => $item->unit,
                        'sku'                 => $item->productService?->code,
                        'notes'               => $item->notes,
                    ]);
                }

                $ocd->submit();

                return $ocd;
            });

            $ocds->push($ocd);
        }

        return $ocds;
    }
}
