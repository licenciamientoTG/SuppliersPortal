<?php

namespace App\Services;

use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\QuotationSummary;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AuthorizationInboxService
{
    public function __construct(private ApprovalDelegationService $delegations) {}

    public function itemsFor(User $user, Request $request): Collection
    {
        $principalIds = $this->delegations->accessiblePrincipalIds($user);
        $scope = $request->string('scope')->toString() ?: 'all';
        $type = $request->string('type')->toString() ?: 'all';
        $days = $request->integer('days');

        if ($scope === 'own') {
            $principalIds = [$user->id];
        } elseif ($scope === 'delegated') {
            $principalIds = array_values(array_diff($principalIds, [$user->id]));
        }

        $applyAge = function ($query) use ($days) {
            if ($days > 0) {
                $query->where('created_at', '<=', now()->subDays($days));
            }
        };

        $items = collect();

        if (in_array($type, ['all', 'quotation'], true)) {
            $query = QuotationSummary::query()
                ->with(['rfq', 'requisition', 'selectedSupplier', 'currentApprover'])
                ->where('approval_status', 'pending')
                ->whereIn('current_approver_user_id', $principalIds);
            $applyAge($query);

            $items = $items->merge($query->get()->map(fn (QuotationSummary $summary) => [
                'key' => 'quotation-'.$summary->id,
                'type' => 'quotation',
                'type_label' => 'Compra regular',
                'folio' => $summary->rfq?->folio ?? 'RFQ #'.$summary->id,
                'reference' => $summary->requisition?->folio,
                'supplier' => $summary->selectedSupplier?->company_name,
                'total' => (float) $summary->total,
                'currency' => 'MXN',
                'created_at' => $summary->created_at,
                'principal' => $summary->currentApprover,
                'is_delegated' => (int) $summary->current_approver_user_id !== (int) $user->id,
                'url' => route('approvals.quotations.index', ['summary' => $summary->id]),
            ]));
        }

        if (in_array($type, ['all', 'direct'], true)) {
            $query = DirectPurchaseOrder::query()
                ->with(['supplier', 'assignedApprover'])
                ->where('status', 'PENDING_APPROVAL')
                ->whereIn('assigned_approver_id', $principalIds);
            $applyAge($query);

            $items = $items->merge($query->get()->map(fn (DirectPurchaseOrder $order) => [
                'key' => 'direct-'.$order->id,
                'type' => 'direct',
                'type_label' => 'OC Directa',
                'folio' => $order->folio,
                'reference' => null,
                'supplier' => $order->supplier?->company_name,
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'created_at' => $order->created_at,
                'principal' => $order->assignedApprover,
                'is_delegated' => (int) $order->assigned_approver_id !== (int) $user->id,
                'url' => route('direct-purchase-orders.show', $order),
            ]));
        }

        if (in_array($type, ['all', 'contract'], true)) {
            $query = PurchaseOrder::query()
                ->with(['supplier', 'assignedApprover'])
                ->where('status', 'PENDING_APPROVAL')
                ->whereIn('assigned_approver_id', $principalIds);
            $applyAge($query);

            $items = $items->merge($query->get()->map(fn (PurchaseOrder $order) => [
                'key' => 'contract-'.$order->id,
                'type' => 'contract',
                'type_label' => 'OC por convenio',
                'folio' => $order->folio,
                'reference' => null,
                'supplier' => $order->supplier?->company_name,
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'created_at' => $order->created_at,
                'principal' => $order->assignedApprover,
                'is_delegated' => (int) $order->assigned_approver_id !== (int) $user->id,
                'url' => route('purchase-orders.show', $order),
            ]));
        }

        return $items->sortByDesc('created_at')->values();
    }

    public function countFor(User $user): int
    {
        $principalIds = $this->delegations->accessiblePrincipalIds($user);

        return QuotationSummary::query()->where('approval_status', 'pending')->whereIn('current_approver_user_id', $principalIds)->count()
            + DirectPurchaseOrder::query()->where('status', 'PENDING_APPROVAL')->whereIn('assigned_approver_id', $principalIds)->count()
            + PurchaseOrder::query()->where('status', 'PENDING_APPROVAL')->whereIn('assigned_approver_id', $principalIds)->count();
    }
}
