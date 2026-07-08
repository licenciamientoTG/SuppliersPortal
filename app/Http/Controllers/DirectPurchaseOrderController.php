<?php

namespace App\Http\Controllers;

use App\Enum\PurchaseType;
use App\Http\Requests\SaveDirectPurchaseOrderRequest;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderDocument;
use App\Models\DirectPurchaseOrderItem;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\ReceivingLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\DirectPurchaseOrderApprovedNotification;
use App\Notifications\DirectPurchaseOrderRejectedNotification;
use App\Notifications\DirectPurchaseOrderReturnedNotification;
use App\Notifications\NewDirectPurchaseOrderNotification;
use App\Services\AuthorizerResolutionService;
use App\Services\BudgetAllocationService;
use App\Services\ProductBudgetClassificationService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DirectPurchaseOrderController extends Controller
{
    public function __construct(
        private PricingService $pricingService,
        private BudgetAllocationService $budgetAllocationService,
        private AuthorizerResolutionService $authorizerResolutionService
    ) {
    }

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

        $products = $this->productCatalogForDirectPurchaseOrders();

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
            'products',
            'receivingLocations'
        ))->with([
            'purchaseTypes' => PurchaseType::values(),
        ]);
    }

    public function store(SaveDirectPurchaseOrderRequest $request)
    {
        try {
            DB::beginTransaction();

            $totals = $this->pricingService->calculateTotals($request->items);
            $supplier = Supplier::active()->findOrFail($request->supplier_id);
            $estimatedDays = (int) ($request->estimated_delivery_days ?? $supplier->avg_delivery_time ?? 30);
            $applicationMonth = now()->addDays($estimatedDays)->format('Y-m');

            $ocd = DirectPurchaseOrder::create([
                'folio' => DirectPurchaseOrder::generateNextFolio(),
                'supplier_id' => $request->supplier_id,
                'receiving_location_id' => $request->receiving_location_id,
                'application_month' => $applicationMonth,
                'justification' => $request->justification,
                'subtotal' => $totals['subtotal'],
                'iva_amount' => $totals['iva'],
                'total' => $totals['total'],
                'currency' => 'MXN',
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

        if (! $directPurchaseOrder->canBeApproved()) {
            return back()->withErrors(['error' => 'Esta OCD no puede ser aprobada. Estado actual: '.$directPurchaseOrder->getStatusLabel()]);
        }

        try {
            DB::beginTransaction();

            if (! $this->hasActiveBudgetReservation($directPurchaseOrder)) {
                $this->ensureBudgetAvailability($directPurchaseOrder);
                $this->budgetAllocationService->reserveDirectPurchaseOrder($directPurchaseOrder);
            }

            $directPurchaseOrder->update([
                'status' => 'ISSUED',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $this->budgetAllocationService->commitOrder($directPurchaseOrder);

            $directPurchaseOrder->approvals()->create([
                'approval_level' => $directPurchaseOrder->required_approval_level ?? 0,
                'approver_user_id' => Auth::id(),
                'action' => 'APPROVED',
                'comments' => $request->input('comments'),
                'approved_at' => now(),
            ]);

            if ($directPurchaseOrder->supplier) {
                $directPurchaseOrder->supplier->notify(new DirectPurchaseOrderApprovedNotification($directPurchaseOrder));
            }

            DB::commit();

            return redirect()
                ->route('direct-purchase-orders.show', $directPurchaseOrder->id)
                ->with('success', 'La Orden de Compra fue aprobada, la reserva quedó confirmada y el proveedor fue notificado.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Error al aprobar la OCD: '.$e->getMessage()]);
        }
    }

    public function reject(Request $request, DirectPurchaseOrder $directPurchaseOrder)
    {
        abort_unless($directPurchaseOrder->isApproverFor(Auth::user()), 403);

        if (! $directPurchaseOrder->isPendingApproval()) {
            return back()->withErrors(['error' => 'Solo se pueden rechazar OCD pendientes de aprobación.']);
        }

        $request->validate([
            'comments' => 'required|string|min:50|max:500',
        ]);

        try {
            DB::beginTransaction();

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

            $creator = $directPurchaseOrder->creator;
            if ($creator) {
                $creator->notify(new DirectPurchaseOrderRejectedNotification($directPurchaseOrder, $request->comments));
            }

            $buyers = User::role('buyer')->get();
            foreach ($buyers as $buyer) {
                if ($buyer->id !== Auth::id()) {
                    $buyer->notify(new DirectPurchaseOrderRejectedNotification($directPurchaseOrder, $request->comments));
                }
            }

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
            if ($creator) {
                $creator->notify(new DirectPurchaseOrderReturnedNotification($directPurchaseOrder, $request->comments));
            }

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

        $products = $this->productCatalogForDirectPurchaseOrders();

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
            'products',
            'receivingLocations'
        ))->with([
            'purchaseTypes' => PurchaseType::values(),
        ]);
    }

    public function update(SaveDirectPurchaseOrderRequest $request, DirectPurchaseOrder $directPurchaseOrder)
    {
        try {
            DB::beginTransaction();

            $wasReturned = $directPurchaseOrder->isReturned();
            $totals = $this->pricingService->calculateTotals($request->items);
            $supplier = Supplier::active()->findOrFail($request->supplier_id);
            $estimatedDays = (int) ($request->estimated_delivery_days ?? $supplier->avg_delivery_time ?? 30);
            $applicationMonth = now()->addDays($estimatedDays)->format('Y-m');

            $updateData = [
                'supplier_id' => $request->supplier_id,
                'receiving_location_id' => $request->receiving_location_id,
                'application_month' => $applicationMonth,
                'justification' => $request->justification,
                'subtotal' => $totals['subtotal'],
                'iva_amount' => $totals['iva'],
                'total' => $totals['total'],
                'currency' => 'MXN',
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
                'message' => 'Error al obtener categorías: '.$e->getMessage(),
                'categories' => [],
            ], 500);
        }
    }

    private function prepareApprovalFlow(DirectPurchaseOrder $directPurchaseOrder): void
    {
        $directPurchaseOrder->loadMissing('creator.employee', 'items', 'items.costCenter');

        if ($this->hasActiveBudgetReservation($directPurchaseOrder)) {
            $this->budgetAllocationService->releaseDirectPurchaseOrder($directPurchaseOrder);
        }

        $this->ensureBudgetAvailability($directPurchaseOrder);

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

        foreach ($directPurchaseOrder->items as $item) {
            $budgetCheck = $this->validateBudgetAvailability(
                $item->cost_center_id,
                $directPurchaseOrder->application_month,
                (int) $item->expense_category_id,
                (float) $item->total,
                $item->budget_cedula_id ? (int) $item->budget_cedula_id : null
            );

            if (! ($budgetCheck['available'] ?? false)) {
                throw new \RuntimeException('Presupuesto insuficiente: '.$budgetCheck['message']);
            }
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
            $approver->notify(new NewDirectPurchaseOrderNotification($directPurchaseOrder));
        }
    }

    private function syncItems(DirectPurchaseOrder $directPurchaseOrder, array $items): void
    {
        $directPurchaseOrder->items()->delete();

        foreach ($items as $itemData) {
            DirectPurchaseOrderItem::create([
                'direct_purchase_order_id' => $directPurchaseOrder->id,
                'product_service_id'       => $itemData['product_service_id'],
                'cost_center_id'           => $itemData['cost_center_id'],
                'expense_category_id'      => $itemData['expense_category_id'],
                'budget_cedula_id'         => $itemData['budget_cedula_id'],
                'description'              => $itemData['description'],
                'quantity'                 => $itemData['quantity'],
                'unit_price'               => $itemData['unit_price'],
                'iva_rate'                 => $itemData['iva_rate'],
                'unit_of_measure'          => $itemData['unit_of_measure'] ?? null,
                'sku'                      => $itemData['sku'] ?? null,
                'notes'                    => $itemData['notes'] ?? null,
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

    private function productCatalogForDirectPurchaseOrders()
    {
        $classificationService = app(ProductBudgetClassificationService::class);

        return ProductService::query()
            ->forRequisitions()
            ->with(['subaccounts.account', 'budgetCedulas'])
            ->orderBy('short_name')
            ->orderBy('technical_description')
            ->get()
            ->map(function (ProductService $product) use ($classificationService) {
                try {
                    $classification = $classificationService->resolveForProduct($product);
                } catch (\RuntimeException) {
                    return null;
                }

                return [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->short_name ?: $product->technical_description,
                    'description' => $product->getRequisitionDescription(),
                    'unit_of_measure' => $product->unit_of_measure,
                    'sku' => $product->code,
                    'estimated_price' => (float) $product->estimated_price,
                    'expense_category_id' => $classification['expense_category_id'],
                    'expense_category_name' => $classification['expense_category_name'],
                    'budget_cedula_id' => $classification['budget_cedula_id'],
                    'budget_cedula_name' => $classification['budget_cedula_name'],
                    'account_name' => $classification['account_name'],
                    'subaccount_name' => $classification['subaccount_name'],
                ];
            })
            ->filter()
            ->values();
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
