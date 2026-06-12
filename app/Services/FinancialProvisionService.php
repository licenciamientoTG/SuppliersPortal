<?php

namespace App\Services;

use App\Models\DirectPurchaseOrder;
use App\Models\FinancialProvision;
use App\Models\FinancialProvisionAdjustment;
use App\Models\PurchaseOrder;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Notifications\FinancialProvisionDiscrepancyNotification;
use App\Notifications\FinancialProvisionPendingInvoiceNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class FinancialProvisionService
{
    public const DISCREPANCY_TOLERANCE = 0.01;

    public function createForReception(Reception $reception): FinancialProvision
    {
        $reception->loadMissing('items.receivableItem', 'receivable.supplier');
        $order = $reception->receivable;

        return DB::transaction(function () use ($reception, $order) {
            $provision = FinancialProvision::firstOrNew(['reception_id' => $reception->id]);
            if (! $provision->exists) {
                $provision->fill([
                    'receivable_type' => get_class($order),
                    'receivable_id' => $order->id,
                    'supplier_id' => $order->supplier_id,
                    'cost_center_id' => $this->resolveCostCenterId($order),
                    'application_month' => $this->resolveApplicationMonth($order),
                    'provision_amount' => $this->calculateProvisionAmount($reception),
                    'currency' => $order->currency ?? 'MXN',
                    'status' => FinancialProvision::STATUS_PENDING_INVOICE,
                    'provisioned_at' => now(),
                ])->save();
            }

            $invoice = $this->findCompatibleInvoice($provision);
            if ($invoice) {
                $this->reconcile($provision->fresh(), $invoice);
            } else {
                $this->notifyAccounting(new FinancialProvisionPendingInvoiceNotification($provision->fresh()));
            }

            return $provision->fresh(['invoice', 'reception']);
        });
    }

    public function calculateProvisionAmount(Reception $reception): float
    {
        $reception->loadMissing('items.receivableItem');

        return round($reception->items->sum(function (ReceptionItem $item) {
            if ($item->conformity !== ReceptionItem::CONFORMITY_OK) {
                return 0;
            }

            $orderItem = $item->receivableItem;
            if (! $orderItem) {
                return 0;
            }

            $quantity = (float) $item->quantity_received;
            $unitPrice = (float) $orderItem->unit_price;
            $subtotal = round($quantity * $unitPrice, 2);
            $ivaRate = $this->resolveIvaRate($orderItem);

            return $subtotal + round($subtotal * ($ivaRate / 100), 2);
        }), 2);
    }

    public function findCompatibleInvoice(FinancialProvision $provision): ?SupplierInvoice
    {
        return SupplierInvoice::where('supplier_id', $provision->supplier_id)
            ->where('receivable_type', $provision->receivable_type)
            ->where('receivable_id', $provision->receivable_id)
            ->whereNull('financial_provision_id')
            ->where('status', SupplierInvoice::STATUS_UPLOADED)
            ->oldest()
            ->first();
    }

    public function reconcile(FinancialProvision $provision, SupplierInvoice $invoice): FinancialProvision
    {
        return DB::transaction(function () use ($provision, $invoice) {
            $difference = round((float) $invoice->total - (float) $provision->provision_amount, 2);
            $hasDiscrepancy = abs($difference) >= self::DISCREPANCY_TOLERANCE;

            $provision->update([
                'supplier_invoice_id' => $invoice->id,
                'invoice_amount' => $invoice->total,
                'difference_amount' => $difference,
                'status' => $hasDiscrepancy
                    ? FinancialProvision::STATUS_DISCREPANCY_REVIEW
                    : FinancialProvision::STATUS_INVOICED,
                'invoiced_at' => now(),
                'closed_at' => $hasDiscrepancy ? null : now(),
            ]);

            $invoice->update([
                'financial_provision_id' => $provision->id,
                'status' => SupplierInvoice::STATUS_LINKED,
                'linked_at' => now(),
            ]);

            if ($hasDiscrepancy) {
                $this->notifyAccounting(new FinancialProvisionDiscrepancyNotification($provision->fresh(['invoice', 'reception'])));
            }

            return $provision->fresh(['invoice', 'adjustments']);
        });
    }

    public function authorizeAdjustment(FinancialProvision $provision, User $user, float $amount, string $reason, ?string $notes = null): FinancialProvision
    {
        return DB::transaction(function () use ($provision, $user, $amount, $reason, $notes) {
            FinancialProvisionAdjustment::create([
                'financial_provision_id' => $provision->id,
                'supplier_invoice_id' => $provision->supplier_invoice_id,
                'authorized_by' => $user->id,
                'amount' => $amount,
                'reason' => $reason,
                'notes' => $notes,
                'authorized_at' => now(),
            ]);

            $provision->update([
                'status' => FinancialProvision::STATUS_CLOSED_WITH_ADJUSTMENT,
                'closed_at' => now(),
            ]);

            return $provision->fresh(['invoice', 'adjustments']);
        });
    }

    private function resolveIvaRate(Model $orderItem): float
    {
        if (isset($orderItem->iva_rate)) {
            return (float) $orderItem->iva_rate;
        }

        $subtotal = (float) $orderItem->subtotal;
        if ($subtotal <= 0) {
            return 0;
        }

        return round(((float) $orderItem->iva_amount / $subtotal) * 100, 2);
    }

    private function resolveCostCenterId(Model $order): ?int
    {
        if ($order instanceof DirectPurchaseOrder) {
            return $order->cost_center_id;
        }

        if ($order instanceof PurchaseOrder) {
            $order->loadMissing('requisition');
            return $order->requisition?->cost_center_id;
        }

        return null;
    }

    private function resolveApplicationMonth(Model $order): ?string
    {
        if ($order instanceof DirectPurchaseOrder) {
            return $order->application_month;
        }

        return $order->created_at?->format('Y-m');
    }

    private function notifyAccounting(object $notification): void
    {
        try {
            $users = User::role('accounting')->get();
            if ($users->isNotEmpty()) {
                Notification::send($users, $notification);
            }
        } catch (Throwable $exception) {
            Log::error('Failed to notify accounting from financial provision workflow.', [
                'notification' => get_class($notification),
                'exception' => $exception,
            ]);
        }
    }
}
