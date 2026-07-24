<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Notifications\ContractPurchaseOrderPendingApprovalNotification;
use App\Notifications\PurchaseOrderIssuedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ContractPurchaseOrderService
{
    public function __construct(
        private readonly BudgetAllocationService $budgetAllocationService,
        private readonly AuthorizerResolutionService $authorizerResolutionService,
        private readonly ApprovalDelegationService $approvalDelegations
    ) {
    }

    /**
     * Genera una PurchaseOrder por cada proveedor distinto en la requisicion.
     * Requiere que todos los items tengan contract_id, unit_price snapshot y
     * una sola moneda por proveedor.
     */
    public function generateFromRequisition(Requisition $requisition): Collection
    {
        return DB::transaction(function () use ($requisition) {
            $this->assertPurchaseOrderSchemaCompatibility();

            $requisition->loadMissing('items.contract.supplier', 'requester');

            $itemsBySupplier = $requisition->items
                ->groupBy(fn (RequisitionItem $item) => $item->contract?->supplier_id);

            $this->ensureSingleCurrencyPerSupplier($itemsBySupplier);

            $issuedAt = now();
            $purchaseOrders = collect();

            foreach ($itemsBySupplier as $supplierId => $items) {
                $first = $items->first();
                $supplier = $first?->contract?->supplier;

                if (! $supplierId || ! $supplier) {
                    throw new RuntimeException('No fue posible resolver el proveedor para una de las partidas del contrato.');
                }

                $subtotal = round($items->sum(fn (RequisitionItem $item) => (float) $item->unit_price * (float) $item->quantity), 2);
                $iva = round($subtotal * 0.16, 2);
                $total = round($subtotal + $iva, 2);

                // Regla de negocio: las compras contra convenio de precios comprometen
                // gasto nuevo, así que requieren autorización según la matriz. Las
                // igualas ya fueron autorizadas al contratar y se emiten directo.
                $requiresApproval = $items->contains(
                    fn (RequisitionItem $item) => $item->contract?->isConvenio() ?? false
                );

                $poData = [
                    'folio' => $this->nextPoFolio(),
                    'requisition_id' => $requisition->id,
                    'supplier_id' => $supplierId,
                    'quotation_summary_id' => null,
                    'source_type' => 'contract',
                    'receiving_location_id' => $requisition->receiving_location_id,
                    'subtotal' => $subtotal,
                    'iva_amount' => $iva,
                    'total' => $total,
                    'currency' => $first->currency_code ?? 'MXN',
                    'payment_terms' => $supplier->default_payment_terms,
                    'estimated_delivery_days' => 0,
                    'created_by' => Auth::id() ?? $requisition->created_by,
                ];

                if ($requiresApproval) {
                    $requester = $requisition->requester ?? $requisition->creator;
                    $resolution = $this->authorizerResolutionService->resolveForRequester($requester, $total);

                    $poData += [
                        'status' => 'PENDING_APPROVAL',
                        'assigned_approver_id' => $resolution['approver_user']->id,
                        'authorizer_role_id' => $resolution['authorizer_role']->id,
                        'effective_authorization_limit' => $resolution['effective_limit'],
                        'approval_chain_snapshot' => json_encode($resolution['chain']),
                        'resolution_notes' => $resolution['resolution_notes'],
                    ];
                } else {
                    $poData += [
                        'status' => 'ISSUED',
                        'approved_at' => $issuedAt,
                        'issued_at' => $issuedAt,
                    ];
                }

                $po = PurchaseOrder::create($poData);

                foreach ($items as $item) {
                    $lineSubtotal = round((float) $item->unit_price * (float) $item->quantity, 2);
                    $lineIva = round($lineSubtotal * 0.16, 2);

                    $po->items()->create([
                        'requisition_item_id' => $item->id,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'subtotal' => $lineSubtotal,
                        'iva_amount' => $lineIva,
                        'total' => round($lineSubtotal + $lineIva, 2),
                    ]);
                }

                // El compromiso presupuestal y el aviso al proveedor se difieren a la
                // autorización cuando la OC queda pendiente.
                if (! $requiresApproval) {
                    $this->budgetAllocationService->commitOrder($po);
                }
                $purchaseOrders->push($po);

                DB::afterCommit(function () use ($po, $requiresApproval) {
                    try {
                        $po->loadMissing('supplier', 'creator', 'requisition.requester', 'assignedApprover');
                        if ($requiresApproval) {
                            $principal = $po->assignedApprover;
                            if ($principal) {
                                $this->approvalDelegations->recipientsForPrincipal($principal)
                                    ->each(fn ($recipient) => $recipient->notify(
                                        new ContractPurchaseOrderPendingApprovalNotification(
                                            $po,
                                            (int) $recipient->id === (int) $principal->id ? null : $principal
                                        )
                                    ));
                            }
                        } else {
                            $po->supplier?->notify(new PurchaseOrderIssuedNotification($po));
                        }
                    } catch (\Throwable $exception) {
                        Log::error('Failed to notify about contract purchase order.', [
                            'purchase_order_id' => $po->id,
                            'folio' => $po->folio,
                            'supplier_id' => $po->supplier_id,
                            'exception' => $exception,
                        ]);
                    }
                });
            }

            $requisition->update([
                'status' => 'COMPLETED',
                'updated_by' => Auth::id() ?? $requisition->updated_by,
            ]);

            return $purchaseOrders;
        });
    }

    private function ensureSingleCurrencyPerSupplier(Collection $itemsBySupplier): void
    {
        foreach ($itemsBySupplier as $supplierItems) {
            $currencies = $supplierItems
                ->pluck('currency_code')
                ->filter()
                ->unique()
                ->values();

            if ($currencies->count() > 1) {
                throw new RuntimeException('No se permite mezclar monedas distintas para el mismo proveedor dentro de una requisicion por contrato.');
            }
        }
    }

    private function nextPoFolio(): string
    {
        $year = date('Y');
        $prefix = "OC-{$year}-";
        $last = PurchaseOrder::where('folio', 'like', $prefix.'%')
            ->orderBy('folio', 'desc')
            ->lockForUpdate()
            ->value('folio');

        $n = 0;
        if ($last && preg_match('/OC-\d{4}-(\d+)/', $last, $m)) {
            $n = (int) $m[1];
        }

        return sprintf('%s%04d', $prefix, $n + 1);
    }

    private function assertPurchaseOrderSchemaCompatibility(): void
    {
        // INFORMATION_SCHEMA/dbo solo existe en SQL Server; en sqlite (tests) el
        // esquema lo garantizan las migraciones.
        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }

        $schema = DB::selectOne("
            SELECT
                MAX(CASE WHEN COLUMN_NAME = 'source_type' THEN 1 ELSE 0 END) AS has_source_type,
                MAX(CASE WHEN COLUMN_NAME = 'quotation_summary_id' AND IS_NULLABLE = 'YES' THEN 1 ELSE 0 END) AS quotation_summary_nullable
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'purchase_orders'
              AND TABLE_SCHEMA = 'dbo'
        ");

        if (! $schema || ! (int) ($schema->has_source_type ?? 0)) {
            throw new RuntimeException(
                'La tabla purchase_orders no tiene la columna source_type. Falta ejecutar la migracion 2026_06_09_000005_make_quotation_summary_nullable_in_purchase_orders.'
            );
        }

        if (! (int) ($schema->quotation_summary_nullable ?? 0)) {
            throw new RuntimeException(
                'La columna purchase_orders.quotation_summary_id sigue obligatoria. Falta ejecutar la migracion 2026_06_09_000005_make_quotation_summary_nullable_in_purchase_orders.'
            );
        }
    }
}
