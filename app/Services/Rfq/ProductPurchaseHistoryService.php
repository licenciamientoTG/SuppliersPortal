<?php

namespace App\Services\Rfq;

use App\Models\PurchaseOrderItem;
use Illuminate\Support\Collection;

class ProductPurchaseHistoryService
{
    /**
     * Últimas compras del mismo producto, sin importar proveedor.
     * Devuelve sólo órdenes emitidas o posteriores para no sugerir precios que
     * nunca llegaron a convertirse en una compra real.
     */
    public function latestForProduct(int $productServiceId, int $limit = 10): Collection
    {
        return PurchaseOrderItem::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->join('requisition_items', 'requisition_items.id', '=', 'purchase_order_items.requisition_item_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('requisition_items.product_service_id', $productServiceId)
            ->whereIn('purchase_orders.status', ['ISSUED', 'PARTIALLY_RECEIVED', 'RECEIVED', 'PAID', 'CLOSED_BY_INACTIVITY'])
            ->orderByDesc('purchase_orders.issued_at')
            ->orderByDesc('purchase_orders.id')
            ->limit($limit)
            ->get([
                'purchase_order_items.id',
                'purchase_order_items.unit_price',
                'purchase_order_items.iva_amount',
                'purchase_order_items.subtotal',
                'purchase_orders.folio',
                'purchase_orders.supplier_id',
                'purchase_orders.currency',
                'purchase_orders.payment_terms',
                'purchase_orders.estimated_delivery_days',
                'purchase_orders.issued_at',
                'purchase_orders.created_at as ordered_at',
                'suppliers.company_name as supplier_name',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'supplier_id' => (int) $row->supplier_id,
                'supplier_name' => $row->supplier_name,
                'folio' => $row->folio,
                'unit_price' => (float) $row->unit_price,
                'iva_rate' => (float) $row->subtotal > 0
                    ? round(((float) $row->iva_amount / (float) $row->subtotal) * 100, 2)
                    : 0,
                'currency' => $row->currency ?: 'MXN',
                'delivery_days' => $row->estimated_delivery_days !== null ? (int) $row->estimated_delivery_days : null,
                'payment_terms' => $row->payment_terms,
                'ordered_at' => $row->issued_at ?? $row->ordered_at,
            ]);
    }
}
