<?php

namespace App\Http\Controllers;

use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RequisitionItem;
use App\Models\User;
use App\Notifications\ContractPurchaseOrderRejectedNotification;
use App\Notifications\PurchaseOrderIssuedNotification;
use App\Services\ApprovalDecisionService;
use App\Services\ApprovalDelegationService;
use App\Services\BudgetAllocationService;
use App\Services\BudgetImpactSnapshotService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class PurchaseOrderController extends Controller
{
    /**
     * Vista principal con tabs para OC Regulares y OCD
     */
    public function index()
    {
        // Obtenemos contadores para mostrar en los tabs
        $regularCount = PurchaseOrder::visibleTo(Auth::user())->count();
        $directCount = DirectPurchaseOrder::visibleTo(Auth::user())->count();

        return view('purchase-orders.index', compact('regularCount', 'directCount'));
    }

    /**
     * Autoriza una OC de contrato (convenio de precios) — solo el aprobador asignado.
     */
    public function approve(
        PurchaseOrder $purchaseOrder,
        BudgetAllocationService $budgetAllocationService,
        ApprovalDelegationService $approvalDelegations,
        ApprovalDecisionService $approvalDecisions
    ) {
        abort_unless($purchaseOrder->isApproverFor(Auth::user()), 403);
        abort_unless($purchaseOrder->isPendingApproval(), 422, 'La OC no está pendiente de autorización.');

        $principalId = (int) $purchaseOrder->assigned_approver_id;

        DB::transaction(function () use (&$purchaseOrder, $budgetAllocationService, $approvalDelegations, $approvalDecisions, $principalId) {
            $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            abort_unless($purchaseOrder->isPendingApproval(), 422, 'Esta autorización ya fue resuelta por otro integrante.');
            abort_unless($approvalDelegations->canAct(Auth::user(), $principalId), 403);
            $now = now();

            $budgetAllocationService->commitOrder($purchaseOrder);

            $purchaseOrder->forceFill([
                'status' => 'ISSUED',
                'approved_by' => Auth::id(),
                'approved_at' => $now,
                'issued_at' => $now,
            ])->save();

            $purchaseOrder->approvals()->create([
                'approver_user_id' => Auth::id(),
                'action' => 'APPROVED',
                'approved_at' => $now,
            ]);
            $approvalDecisions->record($purchaseOrder, $principalId, Auth::user(), 'APPROVED');

            DB::afterCommit(function () use ($purchaseOrder) {
                try {
                    $purchaseOrder->loadMissing('supplier', 'creator');
                    app(\App\Services\SafeNotificationService::class)->notify(
                        new PurchaseOrderIssuedNotification($purchaseOrder),
                        array_filter([$purchaseOrder->supplier]),
                        'de OC de contrato emitida',
                        $purchaseOrder->folio,
                    );
                } catch (\Throwable $exception) {
                    Log::error('Failed to notify supplier about approved contract purchase order.', [
                        'purchase_order_id' => $purchaseOrder->id,
                        'exception' => $exception,
                    ]);
                }
            });
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', "OC {$purchaseOrder->folio} autorizada y emitida al proveedor.");
    }

    /**
     * Rechaza una OC de contrato pendiente — solo el aprobador asignado.
     */
    public function reject(
        Request $request,
        PurchaseOrder $purchaseOrder,
        ApprovalDelegationService $approvalDelegations,
        ApprovalDecisionService $approvalDecisions
    ) {
        abort_unless($purchaseOrder->isApproverFor(Auth::user()), 403);
        abort_unless($purchaseOrder->isPendingApproval(), 422, 'La OC no está pendiente de autorización.');

        $principalId = (int) $purchaseOrder->assigned_approver_id;

        $request->validate([
            'comments' => ['required', 'string', 'min:50', 'max:500'],
        ]);

        DB::transaction(function () use (&$purchaseOrder, $request, $approvalDelegations, $approvalDecisions, $principalId) {
            $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            abort_unless($purchaseOrder->isPendingApproval(), 422, 'Esta autorización ya fue resuelta por otro integrante.');
            abort_unless($approvalDelegations->canAct(Auth::user(), $principalId), 403);
            $purchaseOrder->forceFill([
                'status' => 'REJECTED',
                'rejected_by' => Auth::id(),
                'rejected_at' => now(),
                'assigned_approver_id' => null,
            ])->save();

            $purchaseOrder->approvals()->create([
                'approver_user_id' => Auth::id(),
                'action' => 'REJECTED',
                'comments' => $request->comments,
                'approved_at' => now(),
            ]);
            $approvalDecisions->record(
                $purchaseOrder,
                $principalId,
                Auth::user(),
                'REJECTED',
                $request->comments
            );

            DB::afterCommit(function () use ($purchaseOrder, $request) {
                try {
                    $notification = new ContractPurchaseOrderRejectedNotification($purchaseOrder, $request->comments);
                    $purchaseOrder->loadMissing('creator');
                    app(\App\Services\SafeNotificationService::class)->notify(
                        $notification,
                        array_filter([$purchaseOrder->creator]),
                        'de rechazo de OC de contrato',
                        $purchaseOrder->folio,
                    );

                    User::role('buyer')->get()
                        ->reject(fn (User $u) => $u->id === Auth::id() || $u->id === $purchaseOrder->created_by)
                        ->each(fn (User $user) => app(\App\Services\SafeNotificationService::class)->notify(
                            $notification,
                            [$user],
                            'de rechazo de OC de contrato',
                            $purchaseOrder->folio,
                        ));
                } catch (\Throwable $exception) {
                    Log::error('Failed to notify about rejected contract purchase order.', [
                        'purchase_order_id' => $purchaseOrder->id,
                        'exception' => $exception,
                    ]);
                }
            });
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', "OC {$purchaseOrder->folio} rechazada.");
    }

    /** Anexa una instrucción de Compras a la nota existente de una partida. */
    public function appendSupplierNote(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderItem $purchaseOrderItem)
    {
        abort_unless($request->user()?->hasAnyRole(['buyer', 'superadmin']), 403);
        abort_unless(in_array($purchaseOrder->status, [
            'ISSUED',
            'PARTIALLY_RECEIVED',
            'DELIVERED_PENDING_RECEPTION',
        ], true), 422, 'Solo es posible anexar notas a una OC emitida que sigue en proceso.');

        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $item = $purchaseOrder->items()
            ->whereKey($purchaseOrderItem->id)
            ->firstOrFail();

        abort_unless($item->requisition_item_id, 422, 'La partida no está vinculada a una requisición.');

        DB::transaction(function () use ($data, $item, $purchaseOrder, $request): void {
            $requisitionItem = RequisitionItem::query()
                ->lockForUpdate()
                ->findOrFail($item->requisition_item_id);

            $originalNote = trim((string) $requisitionItem->notes);
            $appendedNote = trim($data['note']);
            $entry = sprintf(
                'Nota adicional de Compras · %s · %s%s%s',
                now()->format('d/m/Y H:i'),
                $request->user()->name,
                PHP_EOL,
                $appendedNote,
            );

            $requisitionItem->update([
                'notes' => $originalNote === '' ? $entry : $originalNote.PHP_EOL.PHP_EOL.$entry,
            ]);

            activity('purchase_orders')
                ->performedOn($purchaseOrder)
                ->causedBy($request->user())
                ->event('supplier_note_appended')
                ->withProperties([
                    'purchase_order_item_id' => $item->id,
                    'requisition_item_id' => $requisitionItem->id,
                    'note' => $appendedNote,
                ])
                ->log('Compras anexó una nota para el proveedor en la partida '.$item->id.'.');
        });

        return back()->with('success', 'La nota adicional de Compras fue anexada a la partida.');
    }

    /**
     * DataTable para Órdenes de Compra REGULARES (Requisición → Cotizaciones → OC)
     */
    public function datatableRegular(Request $request)
    {
        if ($request->ajax()) {
            $purchaseOrders = PurchaseOrder::visibleTo($request->user())
                ->with(['supplier', 'requisition', 'creator'])
                ->select('purchase_orders.*');

            return DataTables::of($purchaseOrders)
                ->addIndexColumn()
                ->addColumn('folio', function ($po) {
                    return '<span class="fw-bold text-dark">'.$po->folio.'</span>';
                })
                ->addColumn('fecha_emision', function ($po) {
                    return $po->created_at->format('d/m/Y H:i');
                })
                ->addColumn('proveedor', function ($po) {
                    return $po->supplier->company_name ?? 'N/A';
                })
                ->addColumn('requisicion', function ($po) {
                    return '<span class="badge bg-soft-secondary text-secondary">'.
                        ($po->requisition->folio ?? 'N/A').
                        '</span>';
                })
                ->addColumn('total', function ($po) {
                    return '<span class="fw-bold text-primary">'.format_money($po->total, $po->currency).'</span>';
                })
                ->addColumn('status', function ($po) {
                    return '<span class="badge bg-'.$po->getStatusBadgeClass().'">'
                        .$po->getStatusLabel().'</span>';
                })
                ->addColumn('actions', function ($po) {
                    $showUrl = route('purchase-orders.show', $po->id);
                    $buttons = '
                        <a href="'.$showUrl.'" class="btn btn-sm btn-outline-primary" title="Ver Detalle">
                            <i class="ti ti-eye"></i>
                        </a>
                    ';

                    if ($po->canBeReceived()) {
                        $receiveUrl = route('receptions.create', $po->id);
                        $buttons .= '
                            <a href="'.$receiveUrl.'" class="btn btn-sm btn-outline-success ms-1" title="Registrar Recepción">
                                <i class="ti ti-package-import"></i>
                            </a>
                        ';
                    }

                    return $buttons;
                })
                ->rawColumns(['folio', 'requisicion', 'total', 'status', 'actions'])
                ->make(true);
        }
    }

    /**
     * DataTable para Órdenes de Compra DIRECTAS (sin proceso de cotización)
     */
    public function datatableDirect(Request $request)
    {
        if ($request->ajax()) {
            $directOrders = DirectPurchaseOrder::visibleTo($request->user())
                ->with(['supplier', 'creator', 'items.costCenter'])
                ->select('odc_direct_purchase_orders.*');

            return DataTables::of($directOrders)
                ->addIndexColumn()
                ->addColumn('folio', function ($ocd) {
                    return '<span class="fw-bold text-dark">'.($ocd->folio ?? 'DRAFT').'</span>';
                })
                ->addColumn('fecha_solicitud', function ($ocd) {
                    return $ocd->created_at->format('d/m/Y H:i');
                })
                ->addColumn('proveedor', function ($ocd) {
                    return $ocd->supplier->company_name ?? 'N/A';
                })
                ->addColumn('solicitante', function ($ocd) {
                    return '<span class="badge bg-soft-info text-info">'.
                        $ocd->creator->name.
                        '</span>';
                })
                ->addColumn('centro_costo', function ($ocd) {
                    return $ocd->primaryCostCenterLabel();
                })
                ->addColumn('total', function ($ocd) {
                    return '<span class="fw-bold text-primary">'.format_money($ocd->total, $ocd->currency).'</span>';
                })
                ->addColumn('status', function ($ocd) {
                    return '<span class="badge bg-'.$ocd->getStatusBadgeClass().'">'
                        .$ocd->getStatusLabel().'</span>';
                })
                ->addColumn('actions', function ($ocd) {
                    $showUrl = route('direct-purchase-orders.show', $ocd->id);

                    $buttons = '
                        <a href="'.$showUrl.'" class="btn btn-sm btn-outline-primary" title="Ver Detalle">
                            <i class="ti ti-eye"></i>
                        </a>
                    ';

                    $canEdit = $ocd->status === 'RETURNED' && (int) $ocd->created_by === (int) Auth::id();

                    if ($canEdit) {
                        $editUrl = route('direct-purchase-orders.edit', $ocd->id);
                        $buttons .= '
                            <a href="'.$editUrl.'" class="btn btn-sm btn-outline-warning ms-1" title="Editar">
                                <i class="ti ti-edit"></i>
                            </a>
                        ';
                    } else {
                        $buttons .= '
                            <a class="btn btn-sm btn-outline-warning ms-1 disabled" aria-disabled="true" title="Editar (solo disponible cuando la OCD está Devuelta)">
                                <i class="ti ti-edit"></i>
                            </a>
                        ';
                    }

                    if ($ocd->canBeReceived()) {
                        $receiveUrl = route('receptions.create-direct', $ocd->id);
                        $buttons .= '
                            <a href="'.$receiveUrl.'" class="btn btn-sm btn-outline-success ms-1" title="Registrar Recepción">
                                <i class="ti ti-package-import"></i>
                            </a>
                        ';
                    }

                    return $buttons;
                })
                ->rawColumns(['folio', 'solicitante', 'total', 'status', 'actions'])
                ->make(true);
        }
    }

    /**
     * Ver detalle de OC Regular
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load([
            'items.requisitionItem',
            'supplier',
            'creator',
            'receivingLocation',
            'receiver',
            'requisition.department',
            'requisition.items.costCenter',
            'requisition.company',
            'requisition.requester',
            'receptions.items.receivableItem',
            'receptions.receiver',
            'receptions.receivingLocation',
            'assignedApprover',
            'authorizerRole',
            'approvals.approver',
            'approvalDecisions.actor',
            'approvalDecisions.principal',
        ]);

        return view('purchase-orders.show', compact('purchaseOrder'));
    }

    /** Download the formal purchase order document once it has been issued. */
    public function downloadPdf(PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('view', $purchaseOrder);

        abort_unless(in_array($purchaseOrder->status, [
            'ISSUED',
            'PARTIALLY_RECEIVED',
            'RECEIVED',
            'PAID',
            'DELIVERED_PENDING_RECEPTION',
        ], true), 422, 'La orden de compra debe estar emitida antes de generar su PDF.');

        $purchaseOrder->load([
            'items.requisitionItem.costCenter',
            'supplier',
            'creator',
            'approver',
            'assignedApprover',
            'quotationSummary.approver',
            'quotationSummary.selector',
            'quotationSummary.rfq.creator',
            'receivingLocation',
            'requisition.company',
            'requisition.requester',
        ]);

        return Pdf::loadView('purchase-orders.pdf', [
            'purchaseOrder' => $purchaseOrder,
            'logoPath' => public_path('images/logos/Logo.png'),
        ])->setPaper('letter')->download('orden-de-compra-'.$purchaseOrder->folio.'.pdf');
    }

    /** Download the formal direct purchase order document once it has been issued. */
    public function downloadDirectPdf(DirectPurchaseOrder $directPurchaseOrder): Response
    {
        $this->authorize('view', $directPurchaseOrder);

        abort_unless(in_array($directPurchaseOrder->status, [
            'ISSUED', 'PARTIALLY_RECEIVED', 'RECEIVED', 'DELIVERED_PENDING_RECEPTION',
        ], true), 422, 'La orden de compra directa debe estar emitida antes de generar su PDF.');

        $directPurchaseOrder->load([
            'items.costCenter.company', 'items.expenseCategory', 'items.budgetCedula',
            'supplier', 'creator', 'approver', 'receiver', 'authorizerRole', 'receivingLocation',
        ]);

        return Pdf::loadView('purchase-orders.direct-pdf', [
            'directPurchaseOrder' => $directPurchaseOrder,
            'company' => $directPurchaseOrder->items->pluck('costCenter.company')->filter()->first(),
            'logoPath' => public_path('images/logos/Logo.png'),
        ])->setPaper('letter')->download('orden-de-compra-directa-'.$directPurchaseOrder->folio.'.pdf');
    }

    /** Download an editable Word-compatible version of an issued direct purchase order. */
    public function downloadDirectWord(DirectPurchaseOrder $directPurchaseOrder): Response
    {
        $this->authorize('view', $directPurchaseOrder);

        abort_unless(in_array($directPurchaseOrder->status, [
            'ISSUED', 'PARTIALLY_RECEIVED', 'RECEIVED', 'DELIVERED_PENDING_RECEPTION',
        ], true), 422, 'La orden de compra directa debe estar emitida antes de generar su documento Word.');

        $directPurchaseOrder->load([
            'items.costCenter.company', 'supplier', 'creator', 'receivingLocation',
        ]);

        return response()->view('purchase-orders.direct-word', [
            'directPurchaseOrder' => $directPurchaseOrder,
            'company' => $directPurchaseOrder->items->pluck('costCenter.company')->filter()->first(),
        ], 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="orden-de-compra-directa-'.$directPurchaseOrder->folio.'.doc"',
        ]);
    }

    /**
     * Ver detalle de OCD
     */
    public function showDirect(DirectPurchaseOrder $directPurchaseOrder, BudgetImpactSnapshotService $budgetImpactSnapshotService)
    {
        $this->authorize('view', $directPurchaseOrder);

        $directPurchaseOrder->load([
            'items.expenseCategory',
            'items.costCenter.company',
            'items.budgetCedula',
            'supplier',
            'creator',
            'receivingLocation',
            'assignedApprover',
            'authorizerRole',
            'approver',
            'rejector',
            'approvals.approver',
            'approvalDecisions.actor',
            'approvalDecisions.principal',
            'documents',
            'receptions.items.receivableItem',
            'receptions.receiver',
            'receptions.receivingLocation',
        ]);

        $budgetSnapshot = $budgetImpactSnapshotService->forDirectPurchaseOrder($directPurchaseOrder);

        $issuingCompany = $directPurchaseOrder->items
            ->pluck('costCenter.company')
            ->filter()
            ->first();

        return view('purchase-orders.show-direct', compact('directPurchaseOrder', 'budgetSnapshot', 'issuingCompany'));
    }
}
