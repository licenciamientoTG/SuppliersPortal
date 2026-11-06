<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveDirectPurchaseOrderRequest;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderDocument;
use App\Models\DirectPurchaseOrderItem;
use App\Models\ExchangeRate;
use App\Models\ExpenseCategory;
use App\Models\ReceivingLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\DirectPurchaseOrderApprovedNotification;
use App\Notifications\DirectPurchaseOrderRejectedNotification;
use App\Notifications\DirectPurchaseOrderReturnedNotification;
use App\Notifications\NewDirectPurchaseOrderNotification;
use App\Services\ApprovalDecisionService;
use App\Services\ApprovalDelegationService;
use App\Services\AuthorizerResolutionService;
use App\Services\BudgetAllocationService;
use App\Services\CostCenterApprovalFlowService;
use App\Services\DirectPurchaseOrderBudgetPreviewService;
use App\Services\PricingService;
use App\Services\SafeNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DirectPurchaseOrderController extends Controller
{
    public function __construct(
        private PricingService $pricingService,
        private BudgetAllocationService $budgetAllocationService,
        private AuthorizerResolutionService $authorizerResolutionService,
        private ApprovalDelegationService $approvalDelegations,
        private ApprovalDecisionService $approvalDecisions,
        private CostCenterApprovalFlowService $costCenterApprovalFlow,
        private SafeNotificationService $safeNotifications,
    ) {}

    public function create()
    {
        $companies = Auth::user()->companies()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $costCenters = Auth::user()->costCenters()
            ->with('company')
            ->whereHas('company', function ($query) {
                $query->where('is_active', true);
            })
            ->where('cost_center_user.is_active', true)
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::active()
            ->orderBy('company_name')
            ->get();

        $expenseCategories = ExpenseCategory::active()
            ->orderBy('name')
            ->get();

        $selectedCompanyId = old('company_id');
        $receivingLocations = $selectedCompanyId
            ? ReceivingLocation::active()
                ->portalUnblocked()
                ->forCompany($selectedCompanyId)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'type', 'city'])
            : collect();

        return view('direct-purchase-orders.create', compact(
            'companies',
            'costCenters',
            'suppliers',
            'expenseCategories',
            'receivingLocations'
        ));
    }

    /**
     * Muestra un adjunto de una OCD desde almacenamiento privado.
     */
    public function showDocument(
        DirectPurchaseOrder $directPurchaseOrder,
        DirectPurchaseOrderDocument $document
    ) {
        $this->authorize('view', $directPurchaseOrder);

        abort_unless(
            (int) $document->direct_purchase_order_id === (int) $directPurchaseOrder->id,
            404
        );

        $disk = Storage::disk('local');

        abort_unless(
            $disk->exists($document->file_path),
            404,
            'El archivo adjunto ya no está disponible.'
        );

        return $disk->response(
            $document->file_path,
            $document->original_filename,
            ['Content-Type' => $disk->mimeType($document->file_path) ?: 'application/octet-stream'],
            'inline'
        );
    }

    public function store(SaveDirectPurchaseOrderRequest $request)
    {
        try {
            DB::beginTransaction();

            $totals = $this->pricingService->calculateTotals($request->items);
            $supplier = Supplier::active()->findOrFail($request->supplier_id);
            $estimatedDays = (int) ($request->estimated_delivery_days ?? $supplier->avg_delivery_time ?? 30);
            $applicationMonth = now()->addDays($estimatedDays)->format('Y-m');
            $currency = $request->input('currency', 'MXN');
            $exchangeRate = ExchangeRate::rateToMxn($currency);

            $ocd = DirectPurchaseOrder::create([
                'folio' => DirectPurchaseOrder::generateNextFolio(),
                'supplier_id' => $request->supplier_id,
                'receiving_location_id' => $request->receiving_location_id,
                'application_month' => $applicationMonth,
                'justification' => $request->justification,
                'subtotal' => $totals['subtotal'],
                'iva_amount' => $totals['iva'],
                'total' => $totals['total'],
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'payment_terms' => $request->payment_terms ?? $supplier->default_payment_terms ?? 'Contado',
                'estimated_delivery_days' => $estimatedDays,
                'required_approval_level' => null,
                'status' => 'PENDING_APPROVAL',
                'created_by' => Auth::id(),
                'submitted_at' => now(),
            ]);

            $this->syncItems($ocd, $request->items);
            $this->syncDocuments($ocd, $request, false);

            $this->prepareApprovalFlow($ocd);

            DB::commit();

            $ocd->refresh()->loadMissing('assignedApprover', 'supplier', 'creator', 'authorizerRole');
            $this->notifyAssignedApprover($ocd);

            return redirect()
                ->route('purchase-orders.index')
                ->with('success', 'Orden de Compra Directa creada y enviada a aprobación bajo el folio: '.$ocd->folio);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al crear OCD: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->except(['quotation_file', 'support_documents']),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al crear la OCD: '.$e->getMessage()]);
        }
    }

    public function submit(DirectPurchaseOrder $directPurchaseOrder)
    {
        $this->authorize('update', $directPurchaseOrder);

        if ((int) $directPurchaseOrder->created_by !== (int) Auth::id()) {
            return back()->withErrors(['error' => 'Solo el creador puede enviar la OCD a aprobación.']);
        }

        if (! $directPurchaseOrder->canBeSubmitted()) {
            return back()->withErrors(['error' => 'Solo se pueden enviar OCD en estado Borrador o Devueltas con al menos una partida.']);
        }

        try {
            DB::beginTransaction();

            $directPurchaseOrder->forceFill([
                'folio' => $directPurchaseOrder->folio ?? DirectPurchaseOrder::generateNextFolio(),
                'status' => 'PENDING_APPROVAL',
                'submitted_at' => now(),
            ])->save();

            $this->prepareApprovalFlow($directPurchaseOrder);

            DB::commit();

            $directPurchaseOrder->refresh()->loadMissing('assignedApprover', 'supplier', 'creator', 'authorizerRole');
            $this->notifyAssignedApprover($directPurchaseOrder);

            return redirect()
                ->route('direct-purchase-orders.show', $directPurchaseOrder->id)
                ->with('success', 'OCD enviada a aprobación exitosamente bajo el folio: '.$directPurchaseOrder->folio);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'No se pudo enviar la OCD: '.$e->getMessage()]);
        }
    }

    public function approve(Request $request, DirectPurchaseOrder $directPurchaseOrder)
    {
        abort_unless($directPurchaseOrder->isApproverFor(Auth::user()), 403);
        $principalId = (int) $directPurchaseOrder->assigned_approver_id;

        if (! $directPurchaseOrder->canBeApproved()) {
            return back()->withErrors(['error' => 'Esta OCD no puede ser aprobada. Estado actual: '.$directPurchaseOrder->getStatusLabel()]);
        }

        try {
            DB::beginTransaction();
            $directPurchaseOrder = DirectPurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($directPurchaseOrder->id);

            if (! $directPurchaseOrder->canBeApproved()) {
                throw new \RuntimeException('Esta autorización ya fue resuelta por otro integrante.');
            }

            abort_unless($this->approvalDelegations->canAct(Auth::user(), $principalId), 403);

            if (! $this->hasActiveBudgetReservation($directPurchaseOrder)) {
                $this->ensureBudgetAvailability($directPurchaseOrder);
                $this->budgetAllocationService->reserveDirectPurchaseOrder($directPurchaseOrder);
            }

            // Solo las OCD con flujo por centro de costo tienen pasos; las que se
            // resolvieron por jerarquía (fallback) se aprueban en un solo paso.
            $usesCostCenterFlow = $directPurchaseOrder->approvalSteps()->where('status', 'PENDING')->exists();

            if ($usesCostCenterFlow && ! $this->costCenterApprovalFlow->advance($directPurchaseOrder, Auth::user(), $request->input('comments'))) {
                DB::commit();

                return redirect()->route('direct-purchase-orders.show', $directPurchaseOrder)->with('success', 'Aprobación registrada; la orden avanzó al siguiente responsable.');
            }
            $directPurchaseOrder->update([
                'status' => 'ISSUED',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'issued_at' => now(),
            ]);

            $this->budgetAllocationService->commitOrder($directPurchaseOrder);

            $directPurchaseOrder->approvals()->create([
                'approval_level' => $directPurchaseOrder->required_approval_level ?? 0,
                'approver_user_id' => Auth::id(),
                'action' => 'APPROVED',
                'comments' => $request->input('comments'),
                'approved_at' => now(),
            ]);
            if (! $usesCostCenterFlow) {
                $this->approvalDecisions->record($directPurchaseOrder, $principalId, Auth::user(), 'APPROVED', $request->input('comments'));
            }

            DB::commit();

            $this->notifySupplierAboutApprovedOrder($directPurchaseOrder);

            return redirect()
                ->route('direct-purchase-orders.show', $directPurchaseOrder->id)
                ->with('success', 'La Orden de Compra fue aprobada y la reserva quedó confirmada.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Error al aprobar la OCD: '.$e->getMessage()]);
        }
    }

    public function reject(Request $request, DirectPurchaseOrder $directPurchaseOrder)
    {
        abort_unless($directPurchaseOrder->isApproverFor(Auth::user()), 403);
        $principalId = (int) $directPurchaseOrder->assigned_approver_id;

        if (! $directPurchaseOrder->isPendingApproval()) {
            return back()->withErrors(['error' => 'Solo se pueden rechazar OCD pendientes de aprobación.']);
        }

        $request->validate([
            'comments' => 'required|string|min:50|max:500',
        ]);

        try {
            DB::beginTransaction();
            $directPurchaseOrder = DirectPurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($directPurchaseOrder->id);

            if (! $directPurchaseOrder->isPendingApproval()) {
                throw new \RuntimeException('Esta autorización ya fue resuelta por otro integrante.');
            }

            abort_unless($this->approvalDelegations->canAct(Auth::user(), $principalId), 403);

            $this->budgetAllocationService->releaseDirectPurchaseOrder($directPurchaseOrder);

            $directPurchaseOrder->update([
                'status' => 'REJECTED',
                'assigned_approver_id' => null,
                'rejected_by' => Auth::id(),
                'rejected_at' => now(),
            ]);

            $directPurchaseOrder->approvals()->create([
                'approval_level' => $directPurchaseOrder->required_approval_level ?? 0,
                'approver_user_id' => Auth::id(),
                'action' => 'REJECTED',
                'comments' => $request->comments,
                'approved_at' => now(),
            ]);
            $this->approvalDecisions->record(
                $directPurchaseOrder,
                $principalId,
                Auth::user(),
                'REJECTED',
                $request->comments
            );

            $creator = $directPurchaseOrder->creator;
            $recipients = User::role('buyer')->get()
                ->when($creator, fn ($users) => $users->prepend($creator))
                ->reject(fn (User $user) => $user->id === Auth::id())
                ->unique('id');
            $this->safeNotifications->notify(
                new DirectPurchaseOrderRejectedNotification($directPurchaseOrder, $request->comments),
                $recipients,
                'de rechazo de OCD',
                $directPurchaseOrder->folio,
                route('direct-purchase-orders.show', $directPurchaseOrder),
            );

            DB::commit();

            return redirect()
                ->route('direct-purchase-orders.show', $directPurchaseOrder->id)
                ->with('rejection_data', [
                    'folio' => $directPurchaseOrder->folio,
                    'total' => $directPurchaseOrder->total,
                    'currency' => $directPurchaseOrder->currency,
                    'comments' => $request->comments,
                    'creator_name' => $creator?->name ?? 'el solicitante',
                ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Error al rechazar la OCD: '.$e->getMessage()]);
        }
    }

    public function return(Request $request, DirectPurchaseOrder $directPurchaseOrder)
    {
        $request->validate(['comments' => 'required|string|max:500']);

        $comingFromIssued = $directPurchaseOrder->status === 'ISSUED';

        if ($comingFromIssued) {
            abort_unless(
                Auth::user()->hasRole('superadmin') || (int) $directPurchaseOrder->approved_by === (int) Auth::id(),
                403
            );
        } else {
            abort_unless($directPurchaseOrder->isApproverFor(Auth::user()), 403);
        }

        if ($comingFromIssued && ! $directPurchaseOrder->canBeReturnedToRevision()) {
            return back()->withErrors(['error' => 'No se puede devolver a revisión una OCD que ya tiene recepciones registradas.']);
        }

        if (! $comingFromIssued && ! $directPurchaseOrder->isPendingApproval()) {
            return back()->withErrors(['error' => 'Esta OCD no puede ser devuelta en su estado actual.']);
        }

        try {
            DB::beginTransaction();

            $this->budgetAllocationService->releaseDirectPurchaseOrder($directPurchaseOrder);

            $directPurchaseOrder->update([
                'status' => 'RETURNED',
                'assigned_approver_id' => null,
                'returned_by' => Auth::id(),
                'returned_at' => now(),
            ]);

            $directPurchaseOrder->approvals()->create([
                'approval_level' => $directPurchaseOrder->required_approval_level ?? 0,
                'approver_user_id' => Auth::id(),
                'action' => 'RETURNED',
                'comments' => $request->comments,
                'approved_at' => now(),
            ]);

            $creator = $directPurchaseOrder->creator;
            $this->safeNotifications->notify(
                new DirectPurchaseOrderReturnedNotification($directPurchaseOrder, $request->comments),
                $creator ? [$creator] : [],
                'de devolución de OCD',
                $directPurchaseOrder->folio,
                route('direct-purchase-orders.show', $directPurchaseOrder),
            );

            DB::commit();

            return redirect()
                ->route('direct-purchase-orders.show', $directPurchaseOrder->id)
                ->with('return_data', [
                    'folio' => $directPurchaseOrder->folio,
                    'creator_name' => $creator?->name ?? 'el solicitante',
                    'comments' => $request->comments,
                ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Error al devolver la OCD: '.$e->getMessage()]);
        }
    }

    public function edit(DirectPurchaseOrder $directPurchaseOrder)
    {
        $this->authorize('update', $directPurchaseOrder);

        if (! $directPurchaseOrder->canBeEdited()) {
            return redirect()
                ->route('purchase-orders.index')
                ->withErrors(['error' => 'Solo se pueden editar OCD en estado Borrador o Devueltas.']);
        }

        if ((int) $directPurchaseOrder->created_by !== (int) Auth::id()) {
            return redirect()
                ->route('purchase-orders.index')
                ->withErrors(['error' => 'Solo puede editar sus propias OCD.']);
        }

        $directPurchaseOrder->load(['items', 'items.costCenter', 'items.productService', 'items.budgetCedula', 'documents']);

        $companies = Auth::user()->companies()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::active()
            ->orderBy('company_name')
            ->get();

        $costCenters = Auth::user()->costCenters()
            ->with('company')
            ->whereHas('company', function ($query) {
                $query->where('is_active', true);
            })
            ->where('cost_center_user.is_active', true)
            ->orderBy('name')
            ->get();

        $expenseCategories = ExpenseCategory::active()
            ->orderBy('name')
            ->get();

        $selectedCompanyId = old('company_id', $directPurchaseOrder->primaryCompanyId());
        $receivingLocations = $selectedCompanyId
            ? ReceivingLocation::active()
                ->portalUnblocked()
                ->forCompany($selectedCompanyId)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'type', 'city'])
            : collect();

        return view('direct-purchase-orders.edit', compact(
            'directPurchaseOrder',
            'companies',
            'suppliers',
            'costCenters',
            'expenseCategories',
            'receivingLocations'
        ));
    }

    public function update(SaveDirectPurchaseOrderRequest $request, DirectPurchaseOrder $directPurchaseOrder)
    {
        $this->authorize('update', $directPurchaseOrder);

        try {
            DB::beginTransaction();

            $wasReturned = $directPurchaseOrder->isReturned();
            $totals = $this->pricingService->calculateTotals($request->items);
            $supplier = Supplier::active()->findOrFail($request->supplier_id);
            $estimatedDays = (int) ($request->estimated_delivery_days ?? $supplier->avg_delivery_time ?? 30);
            $applicationMonth = now()->addDays($estimatedDays)->format('Y-m');
            $currency = $request->input('currency', 'MXN');
            $exchangeRate = ExchangeRate::rateToMxn($currency);

            $updateData = [
                'supplier_id' => $request->supplier_id,
                'receiving_location_id' => $request->receiving_location_id,
                'application_month' => $applicationMonth,
                'justification' => $request->justification,
                'subtotal' => $totals['subtotal'],
                'iva_amount' => $totals['iva'],
                'total' => $totals['total'],
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'payment_terms' => $request->payment_terms ?? $supplier->default_payment_terms ?? 'Contado',
                'estimated_delivery_days' => $estimatedDays,
                'status' => $wasReturned ? 'PENDING_APPROVAL' : 'DRAFT',
                'created_by' => Auth::id(),
            ];

            if ($wasReturned) {
                $updateData['submitted_at'] = now();
            }

            $directPurchaseOrder->update($updateData);

            $this->syncItems($directPurchaseOrder, $request->items);
            $this->syncDocuments($directPurchaseOrder, $request, true);

            if ($wasReturned) {
                $this->prepareApprovalFlow($directPurchaseOrder);
            }

            DB::commit();

            if ($wasReturned) {
                $directPurchaseOrder->refresh()->loadMissing('assignedApprover', 'supplier', 'creator', 'authorizerRole');
                $this->notifyAssignedApprover($directPurchaseOrder);

                return redirect()
                    ->route('direct-purchase-orders.show', $directPurchaseOrder->id)
                    ->with('success', 'OCD actualizada y reenviada a aprobación exitosamente.');
            }

            return redirect()
                ->route('direct-purchase-orders.show', $directPurchaseOrder->id)
                ->with('success', 'OCD actualizada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al actualizar la OCD: '.$e->getMessage()]);
        }
    }

    public function getAvailableCategories(Request $request)
    {
        $request->validate([
            'cost_center_id' => 'required|exists:cost_centers,id',
        ]);

        try {
            $year = now()->year;

            $budget = \App\Models\AnnualBudget::where('cost_center_id', $request->cost_center_id)
                ->where('fiscal_year', $year)
                ->where('status', 'APROBADO')
                ->first();

            if ($budget) {
                $categoryIds = \App\Models\BudgetMonthlyDistribution::where('annual_budget_id', $budget->id)
                    ->distinct()
                    ->pluck('expense_category_id');

                $categories = ExpenseCategory::whereIn('id', $categoryIds)
                    ->active()
                    ->orderBy('name')
                    ->get(['id', 'name']);
            } else {
                $categories = ExpenseCategory::active()
                    ->orderBy('name')
                    ->get(['id', 'name']);
            }

            return response()->json([
                'success' => true,
                'is_free_consumption' => ! $budget,
                'categories' => $categories,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cuentas: '.$e->getMessage(),
                'categories' => [],
            ], 500);
        }
    }

    /**
     * Vista previa del presupuesto disponible por partida mientras se captura la OCD.
     * Solo lectura: no aparta presupuesto ni reemplaza la validación al guardar.
     */
    public function budgetPreview(Request $request, DirectPurchaseOrderBudgetPreviewService $preview)
    {
        $data = $request->validate([
            'currency' => ['nullable', 'string', 'in:MXN,USD,EUR'],
            'supplier_id' => ['nullable', 'integer'],
            'estimated_delivery_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'direct_purchase_order_id' => ['nullable', 'integer'],
            'items' => ['nullable', 'array', 'max:100'],
            'items.*.cost_center_id' => ['nullable', 'integer'],
            'items.*.product_service_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $user = Auth::user();

        $days = $data['estimated_delivery_days']
            ?? (isset($data['supplier_id']) ? Supplier::query()->whereKey($data['supplier_id'])->value('avg_delivery_time') : null);

        $allowedCostCenterIds = $user->costCenters()
            ->where('cost_center_user.is_active', true)
            ->pluck('cost_centers.id')
            ->map(fn ($id) => (int) $id);

        // Solo la OCD propia puede "devolverse" su presupuesto apartado.
        $ocd = isset($data['direct_purchase_order_id'])
            ? DirectPurchaseOrder::query()->whereKey($data['direct_purchase_order_id'])->where('created_by', $user->id)->first()
            : null;

        return response()->json($preview->preview(
            $user,
            $data['items'] ?? [],
            $data['currency'] ?? 'MXN',
            $preview->applicationMonth($days ? (int) $days : null),
            $allowedCostCenterIds,
            $ocd,
        ));
    }

    private function prepareApprovalFlow(DirectPurchaseOrder $directPurchaseOrder): void
    {
        $directPurchaseOrder->loadMissing('creator.employee', 'items', 'items.costCenter');

        if ($this->hasActiveBudgetReservation($directPurchaseOrder)) {
            $this->budgetAllocationService->releaseDirectPurchaseOrder($directPurchaseOrder);
        }

        $this->ensureBudgetAvailability($directPurchaseOrder);

        if ($this->costCenterApprovalFlow->initialize($directPurchaseOrder)) {
            $this->budgetAllocationService->reserveDirectPurchaseOrder($directPurchaseOrder);

            return;
        }
        $resolution = $this->authorizerResolutionService->resolveForDirectPurchaseOrder($directPurchaseOrder);

        $this->budgetAllocationService->reserveDirectPurchaseOrder($directPurchaseOrder);

        $directPurchaseOrder->forceFill([
            'status' => 'PENDING_APPROVAL',
            'required_approval_level' => null,
            'assigned_approver_id' => $resolution['approver_user']->id,
            'authorizer_role_id' => $resolution['authorizer_role']->id,
            'effective_authorization_limit' => $resolution['effective_limit'],
            'approval_chain_snapshot' => $resolution['chain'],
            'resolution_notes' => $resolution['resolution_notes'],
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'returned_by' => null,
            'returned_at' => null,
        ])->save();
    }

    private function ensureBudgetAvailability(DirectPurchaseOrder $directPurchaseOrder): void
    {
        $directPurchaseOrder->loadMissing('items');

        // Misma regla con la que se aparta el presupuesto (getOrderBudgetLines): las
        // partidas que caen en el mismo centro + cuenta + cédula consumen un solo saldo,
        // así que se validan sumadas y no una por una.
        $groups = [];
        foreach ($directPurchaseOrder->items as $item) {
            $itemAmountMxn = $directPurchaseOrder->convertItemAmountToMxn((float) $item->subtotal);
            $cedulaId = $item->budget_cedula_id ? (int) $item->budget_cedula_id : null;

            foreach (app(\App\Services\CostCenterDistributionService::class)->expand((int) $item->cost_center_id, $itemAmountMxn) as $allocation) {
                $key = implode('|', [$allocation['cost_center_id'], $item->expense_category_id, $cedulaId ?? 'null']);

                $groups[$key] ??= [
                    'cost_center_id' => (int) $allocation['cost_center_id'],
                    'expense_category_id' => (int) $item->expense_category_id,
                    'budget_cedula_id' => $cedulaId,
                    'amount' => 0.0,
                    'item_ids' => [],
                ];
                $groups[$key]['amount'] = round($groups[$key]['amount'] + (float) $allocation['amount'], 2);
                $groups[$key]['item_ids'][] = $item->id;
            }
        }

        foreach ($groups as $group) {
            $budgetCheck = $this->validateBudgetAvailability(
                $group['cost_center_id'],
                $directPurchaseOrder->application_month,
                $group['expense_category_id'],
                $group['amount'],
                $group['budget_cedula_id']
            );

            if ($budgetCheck['available'] ?? false) {
                continue;
            }

            // Las excepciones se aprueban por partida; cubren el faltante del grupo en conjunto.
            $approvedExcess = (float) \App\Models\BudgetException::query()
                ->where('document_type', 'direct_purchase_order')
                ->where('document_id', $directPurchaseOrder->id)
                ->whereIn('document_line_id', array_unique($group['item_ids']))
                ->where('cost_center_id', $group['cost_center_id'])
                ->where('status', 'APPROVED')
                ->whereNull('used_at')
                ->sum('approved_excess');

            $available = (float) ($budgetCheck['assigned_amount'] ?? 0) - (float) ($budgetCheck['consumed_amount'] ?? 0) - (float) ($budgetCheck['committed_amount'] ?? 0);
            $shortfall = max(0, round($group['amount'] - $available, 2));

            if ($approvedExcess > 0 && $shortfall <= $approvedExcess + 0.01) {
                continue;
            }

            $lines = count(array_unique($group['item_ids']));
            throw new \RuntimeException('Presupuesto insuficiente'.($lines > 1 ? " (suma de {$lines} partidas con la misma cédula)" : '').': '.$budgetCheck['message']);
        }
    }

    private function hasActiveBudgetReservation(DirectPurchaseOrder $directPurchaseOrder): bool
    {
        return $directPurchaseOrder->budgetCommitments()
            ->where('status', 'COMMITTED')
            ->exists();
    }

    private function notifyAssignedApprover(DirectPurchaseOrder $directPurchaseOrder): void
    {
        $approver = $directPurchaseOrder->assignedApprover;

        if ($approver) {
            $recipients = $this->approvalDelegations->recipientsForPrincipal($approver);
            foreach ($recipients as $recipient) {
                $this->safeNotifications->notify(
                    new NewDirectPurchaseOrderNotification(
                        $directPurchaseOrder,
                        (int) $recipient->id === (int) $approver->id ? null : $approver
                    ),
                    [$recipient],
                    'de solicitud de aprobación de OCD',
                    $directPurchaseOrder->folio,
                    route('direct-purchase-orders.show', $directPurchaseOrder),
                );
            }
        }
    }

    /**
     * El correo es una notificación secundaria: la aprobación ya confirmada
     * nunca debe revertirse por una indisponibilidad del proveedor SMTP.
     */
    private function notifySupplierAboutApprovedOrder(DirectPurchaseOrder $directPurchaseOrder): void
    {
        try {
            $directPurchaseOrder->loadMissing('supplier', 'creator');
            $this->safeNotifications->notify(
                new DirectPurchaseOrderApprovedNotification($directPurchaseOrder),
                User::role('buyer')->get()->concat(
                    $directPurchaseOrder->supplier ? [$directPurchaseOrder->supplier] : []
                ),
                'de aprobación al proveedor',
                $directPurchaseOrder->folio,
                route('direct-purchase-orders.show', $directPurchaseOrder),
                true,
            );
        } catch (\Throwable $exception) {
            // Se ejecuta después del commit: un fallo aquí (p. ej. no existe el rol
            // buyer) no debe mostrarse como "Error al aprobar" ni provocar un rollBack.
            Log::error('No se pudo notificar la aprobación de la OCD.', [
                'folio' => $directPurchaseOrder->folio,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function syncItems(DirectPurchaseOrder $directPurchaseOrder, array $items): void
    {
        $directPurchaseOrder->items()->delete();

        foreach ($items as $itemData) {
            DirectPurchaseOrderItem::create([
                'direct_purchase_order_id' => $directPurchaseOrder->id,
                'product_service_id' => $itemData['product_service_id'],
                'cost_center_id' => $itemData['cost_center_id'],
                'expense_category_id' => $itemData['expense_category_id'],
                'budget_cedula_id' => $itemData['budget_cedula_id'],
                'description' => $itemData['description'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'iva_rate' => $itemData['iva_rate'],
                'unit_of_measure' => $itemData['unit_of_measure'] ?? null,
                'sku' => $itemData['sku'] ?? null,
                'notes' => $itemData['notes'] ?? null,
            ]);
        }
    }

    private function syncDocuments(DirectPurchaseOrder $directPurchaseOrder, SaveDirectPurchaseOrderRequest $request, bool $isUpdate): void
    {
        if ($request->hasFile('quotation_file')) {
            if ($isUpdate) {
                $oldQuotation = $directPurchaseOrder->documents()
                    ->where('document_type', 'quotation')
                    ->first();

                if ($oldQuotation) {
                    $oldQuotation->delete();
                }
            }

            $this->uploadDocument($directPurchaseOrder, $request->file('quotation_file'), 'quotation');
        }

        if ($request->hasFile('support_documents')) {
            foreach ($request->file('support_documents') as $file) {
                $this->uploadDocument($directPurchaseOrder, $file, 'support_document');
            }
        }
    }

    private function validateBudgetAvailability($costCenterId, $monthStr, $categoryId, $requiredAmount, ?int $budgetCedulaId = null): array
    {
        try {
            $costCenter = CostCenter::find($costCenterId);
            if ($costCenter && $costCenter->budget_type === 'FREE_CONSUMPTION') {
                return ['available' => true, 'message' => 'Centro de costo de consumo libre.'];
            }

            $date = Carbon::parse($monthStr.'-01');

            return $this->budgetAllocationService->checkAvailability(
                (int) $costCenterId,
                (int) $date->year,
                (int) $date->month,
                (int) $categoryId,
                (float) $requiredAmount,
                $budgetCedulaId
            );
        } catch (\Exception $e) {
            return [
                'available' => false,
                'message' => 'Error al validar presupuesto: '.$e->getMessage(),
            ];
        }
    }

    private function uploadDocument(DirectPurchaseOrder $ocd, $file, $type): DirectPurchaseOrderDocument
    {
        $year = now()->year;
        $month = now()->format('m');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileName = uniqid('ocd_'.$ocd->id.'_').'.'.$extension;

        $path = $file->storeAs(
            "ocd_documents/{$year}/{$month}",
            $fileName,
            'local'
        );

        return DirectPurchaseOrderDocument::create([
            'direct_purchase_order_id' => $ocd->id,
            'document_type' => $type,
            'original_filename' => $originalName,
            'file_path' => $path,
            'uploaded_by' => Auth::id(),
        ]);
    }
}
