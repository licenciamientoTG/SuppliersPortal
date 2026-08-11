<?php

namespace App\Livewire\Rfq;

use App\Enum\RequisitionStatus;
use App\Exceptions\Rfq\DuplicateSupplierRfcException;
use App\Exceptions\Rfq\IncompleteValidationException;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Services\Rfq\BudgetBlockedNoticeService;
use App\Services\Rfq\ManualQuoteService;
use App\Services\Rfq\ProductPurchaseHistoryService;
use App\Services\Rfq\RequisitionValidationService;
use App\Services\Rfq\RfqDraftService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;

class QuotationWizard extends Component
{
    use WithFileUploads;

    // La requisición con la que trabajaremos
    public Requisition $requisition;

    // Paso actual (1 a 5)
    public $currentStep = 1;

    // Datos que se van recolectando en cada paso
    public $validationData = [];

    public $planningData = [];

    public $suppliersData = [];

    public $rfqData = [];

    public $comparisonData = [];

    public $isReadOnlyAfterSend = false;

    public bool $hasEditableManualBudgetBlockedGroup = false;

    // ======= NUEVO: Datos para el paso 2 =======
    public $unassignedItems = [];

    public $groups = [];

    // ======= Cotización manual del comprador (Paso 3) =======
    public $showManualQuoteModal = false;

    public $manualQuoteGroupId = null;

    public $manualQuoteSupplierId = null; // null = crear proveedor externo nuevo

    public $manualQuoteNewSupplier = [
        'company_name' => '',
        'rfc' => '',
        'postal_code' => '',
        'contact_person' => '',
        'email' => '',
        'phone_number' => '',
    ];

    public $manualQuoteItems = [];

    public $manualQuoteQuotationDate = null;

    public $manualQuoteValidityDays = 30;

    public $manualQuoteAttachment = null;

    public ?string $manualQuoteExistingAttachment = null;

    public bool $manualQuoteEditing = false;

    public bool $showManualBudgetNoticeModal = false;

    public ?int $manualBudgetNoticeSupplierId = null;

    public string $manualBudgetNoticeNote = '';

    public $manualHistoryItemId = null;

    public array $manualPurchaseHistory = [];

    /**
     * Inicializar el wizard con la requisición
     */
    public function mount(Requisition $requisition)
    {
        $this->requisition = $requisition->load([
            'requester',
            'company',
            'department',
            'items.costCenter',
            'items.productService',
            'items.expenseCategory',
            'quotationGroups.items',
            'rfqs',
            'rfqs.suppliers',
            'rfqs.rfqResponses',
        ]);

        $this->refreshReadOnlyAfterSend();

        // Si la URL trae ?step=X, respetar ese valor (ej: al recargar desde paso 2 tras crear grupos)
        $urlStep = request()->integer('step', 0);
        if ($urlStep >= 1 && $urlStep <= 5) {
            $this->currentStep = $urlStep;
        } elseif ($this->currentStep == 1) {
            // Si no hay paso en la URL, determinarlo automáticamente
            $this->currentStep = $this->determineCurrentStep();
        }

        // Cargar datos según el paso en el que quedamos
        $this->loadStepData();

        // Cargar datos de validación si ya fueron validados
        if ($this->requisition->validated_at) {
            $this->validationData = [
                'specs_clear' => $this->requisition->validation_specs_clear,
                'time_feasible' => $this->requisition->validation_time_feasible,
                'alternatives_evaluated' => $this->requisition->validation_alternatives_evaluated,
                'purchasing_notes' => $this->requisition->purchasing_validation_notes,
            ];
        }

        // Cargar datos de planificación si estamos en paso 2 o superior
        if ($this->currentStep >= 2) {
            $this->loadPlanningData();
        }

        // Cargar datos de proveedores si estamos en paso 3 o superior
        if ($this->currentStep >= 3) {
            $this->loadSuppliersData();
        }
    }

    /**
     * Nueva función auxiliar para cargar datos según el paso actual
     */
    public function loadStepData()
    {
        if ($this->currentStep >= 2) {
            $this->loadPlanningData();
        }
        if ($this->currentStep >= 3) {
            $this->createIndividualGroupsForUnassignedItems();
            $this->loadSuppliersData();
        }
    }

    /**
     * Determinar el paso actual basado en el estado de la requisición
     */
    private function determineCurrentStep(): int
    {
        // Solo saltar al paso 5 cuando TODOS los RFQs activos de la requisición ya
        // tienen respuesta (antes bastaba con que UNO solo llegara a RECEIVED, lo que
        // arrastraba todo el wizard al paso 5 aunque otros grupos siguieran pendientes).
        $activeRfqs = $this->requisition->rfqs()->active()->get();
        if ($activeRfqs->isNotEmpty() && $activeRfqs->every(fn ($rfq) => in_array($rfq->status, ['RECEIVED', 'EVALUATED'], true))) {
            return 5;
        }

        // Si hay RFQs creadas, ir al paso 4
        if ($this->requisition->rfqs()->exists()) {
            return 4;
        }

        // Si tiene grupos de cotización, ir al paso 3
        if ($this->requisition->quotationGroups()->active()->exists()) {
            return 3;
        }

        // Si ya fue validada, ir al paso 2
        if ($this->requisition->validated_at) {
            return 2;
        }

        // Por defecto, iniciar en paso 1
        return 1;
    }

    /**
     * Cargar datos para el planificador (Paso 2)
     */
    public function loadPlanningData()
    {
        // Obtener partidas que NO están en ningún grupo
        $this->unassignedItems = $this->requisition->items()
            ->whereDoesntHave('quotationGroups')
            ->with('productService', 'expenseCategory')
            ->get()
            ->toArray();

        // Obtener grupos existentes con sus partidas
        $this->groups = $this->requisition->quotationGroups()
            ->active()
            ->with('items.productService')
            ->get()
            ->toArray();
    }

    /**
     * Completar y guardar el paso 1 (Validación)
     */
    public function completeStep1()
    {
        if (! $this->ensureEditableBeforeSend()) {
            return;
        }

        try {
            app(RequisitionValidationService::class)->sign(
                $this->requisition,
                [
                    'specs_clear' => (bool) ($this->validationData['specs_clear'] ?? false),
                    'time_feasible' => (bool) ($this->validationData['time_feasible'] ?? false),
                    'alternatives_evaluated' => (bool) ($this->validationData['alternatives_evaluated'] ?? false),
                ],
                $this->validationData['purchasing_notes'] ?? null,
                Auth::id()
            );

            // Recargar la requisición con el nuevo estado
            $this->requisition->refresh();

            session()->flash('success', '✅ Requisición validada. Puede proceder con el proceso de cotización.');

            // Avanzar al siguiente paso
            $this->currentStep = 2;
        } catch (IncompleteValidationException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'Error al validar la requisición: '.$e->getMessage());
        }
    }

    /**
     * Ir al siguiente paso (genérico para pasos 2-5)
     */
    public function nextStep()
    {
        $this->refreshReadOnlyAfterSend();

        if ($this->currentStep === 2) {
            $this->createIndividualGroupsForUnassignedItems();

            if (! $this->requisition->quotationGroups()->active()->exists()) {
                session()->flash('error', 'Crea al menos una partida antes de configurar invitaciones.');

                return;
            }
        }

        if ($this->currentStep < 5) {
            $this->currentStep++;

            // Recargar datos según el paso
            $this->loadStepData();
        }
    }

    /**
     * Ir al paso anterior
     */
    public function previousStep()
    {
        $this->refreshReadOnlyAfterSend();

        if ($this->currentStep > 1) {
            $this->currentStep--;

            // Recargar datos según el paso
            $this->loadStepData();
        }
    }

    /**
     * Ir a un paso específico
     */
    public function goToStep($step)
    {
        $this->refreshReadOnlyAfterSend();

        if ($step >= 1 && $step <= 5) {
            $this->currentStep = $step;

            // Recargar datos según el paso
            $this->loadStepData();
        }
    }

    /**
     * Validar el paso actual
     */
    private function validateCurrentStep()
    {
        // Aquí agregaremos validaciones específicas para cada paso
        // Por ahora dejamos la validación básica
    }

    /**
     * Validar y guardar datos del paso 1
     */
    public function validateStep1()
    {
        // Validar que todos los checkboxes estén marcados
        $this->validate([
            'validationData.specs_clear' => 'required|accepted',
            'validationData.time_feasible' => 'required|accepted',
            'validationData.alternatives_evaluated' => 'required|accepted',
        ], [
            'validationData.specs_clear.accepted' => 'Debes verificar la claridad de especificaciones',
            'validationData.time_feasible.accepted' => 'Debes verificar la factibilidad de tiempos',
            'validationData.alternatives_evaluated.accepted' => 'Debes verificar las alternativas',
        ]);

        // Guardar datos y avanzar
        $this->nextStep();
    }

    /**
     * Rechazar y devolver requisición al usuario
     */
    public function rejectRequisition($reason)
    {
        if (! $this->ensureEditableBeforeSend()) {
            return;
        }

        // Validar longitud mínima del motivo
        if (strlen($reason) < 20) {
            session()->flash('error', 'El motivo debe tener al menos 20 caracteres.');

            return;
        }

        try {
            $this->requisition->update([
                'status' => RequisitionStatus::REJECTED,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
                'rejected_by' => Auth::id(),
            ]);

            session()->flash('success', "Requisición {$this->requisition->folio} devuelta al usuario correctamente.");

            return redirect()->route('quotes.index');
        } catch (\Exception $e) {
            session()->flash('error', 'Error al rechazar la requisición: '.$e->getMessage());
        }
    }

    /**
     * Completar y guardar el paso 3 (Selección de Proveedores y Creación de RFQs)
     */
    public function completeStep3($groupsData)
    {
        if (! $this->ensureEditableBeforeSend()) {
            return;
        }

        $groupsData = collect($groupsData)->toArray();
        DB::beginTransaction();

        try {
            $draftService = app(RfqDraftService::class);

            foreach ($groupsData as $groupData) {
                $draftService->syncGroupRfq(
                    $this->requisition,
                    (int) $groupData['group_id'],
                    $groupData['supplier_ids'],
                    $groupData['response_deadline'],
                    $groupData['notes'] ?? null,
                    Auth::id()
                );
            }

            DB::commit();
            $this->currentStep = $this->determineCurrentStep();
            $this->loadStepData();
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Resuelve el proveedor para una cotización manual: reusa el seleccionado por id,
     * o crea uno externo nuevo si no hay id. Si el RFC ya existe, no crea duplicado:
     * agrega un error sugiriendo seleccionar el proveedor existente y retorna null.
     */
    public function resolveManualQuoteSupplier(): ?Supplier
    {
        try {
            return app(ManualQuoteService::class)->resolveSupplier(
                $this->manualQuoteSupplierId ? (int) $this->manualQuoteSupplierId : null,
                $this->manualQuoteNewSupplier
            );
        } catch (DuplicateSupplierRfcException $e) {
            $this->addError('manualQuoteNewSupplier.rfc', $e->getMessage());

            return null;
        }
    }

    public function openManualQuoteModal($quotationGroupId): void
    {
        if (! $this->ensureManualQuoteAllowed((int) $quotationGroupId)) {
            return;
        }

        $group = QuotationGroup::with('items')->findOrFail($quotationGroupId);

        $rfq = $this->requisition->rfqs()
            ->where('quotation_group_id', $group->id)
            ->active()
            ->latest('id')
            ->first();

        $this->manualQuoteGroupId = $group->id;
        $this->manualQuoteEditing = $rfq?->source === 'external' && $rfq->status === 'RECEIVED';
        $this->manualQuoteSupplierId = null;
        $this->manualQuoteNewSupplier = [
            'company_name' => '',
            'rfc' => '',
            'postal_code' => '',
            'contact_person' => '',
            'email' => '',
            'phone_number' => '',
        ];

        $this->manualQuoteItems = [];
        $responsesByItem = $this->manualQuoteEditing
            ? $rfq->rfqResponses()->where('entry_source', 'buyer_manual')->get()->keyBy('requisition_item_id')
            : collect();

        foreach ($group->items as $item) {
            $response = $responsesByItem->get($item->id);
            $this->manualQuoteItems[$item->id] = [
                'not_available' => (bool) ($response?->not_available ?? false),
                'unit_price' => $response?->unit_price,
                'iva_rate' => $response?->iva_rate ?? 16,
                'currency' => $response?->currency ?? 'MXN',
                'delivery_days' => $response?->delivery_days,
                'payment_terms' => $response?->payment_terms,
                'warranty_terms' => $response?->warranty_terms,
                'brand' => $response?->brand,
                'model' => $response?->model,
                'specifications' => $response?->specifications,
            ];
        }

        $firstResponse = $responsesByItem->first();
        $this->manualQuoteSupplierId = $firstResponse?->supplier_id;
        $this->manualQuoteQuotationDate = ($firstResponse?->quotation_date ?? now())->format('Y-m-d');
        $this->manualQuoteValidityDays = $firstResponse?->validity_days ?? 30;
        $this->manualQuoteAttachment = null;
        $this->manualQuoteExistingAttachment = $firstResponse?->attachment_path;
        $this->resetErrorBag();
        $this->showManualQuoteModal = true;
    }

    public function closeManualQuoteModal(): void
    {
        $this->showManualQuoteModal = false;
        $this->manualQuoteEditing = false;
        $this->manualQuoteExistingAttachment = null;
    }

    public function updatedManualQuoteSupplierId($supplierId): void
    {
        if ($supplierId) {
            $this->applyManualSupplierDefaults((int) $supplierId, true);
        }
    }

    public function openManualPurchaseHistory(int $itemId): void
    {
        $item = $this->manualQuoteGroup?->items->firstWhere('id', $itemId);
        $this->manualHistoryItemId = $itemId;
        $this->manualPurchaseHistory = $item?->product_service_id
            ? app(ProductPurchaseHistoryService::class)->latestForProduct((int) $item->product_service_id)->values()->all()
            : [];
    }

    public function closeManualPurchaseHistory(): void
    {
        $this->manualHistoryItemId = null;
        $this->manualPurchaseHistory = [];
    }

    public function applyManualPurchaseHistory(int $historyId): void
    {
        $reference = collect($this->manualPurchaseHistory)->firstWhere('id', $historyId);
        if (! $reference || ! $this->manualHistoryItemId) {
            return;
        }

        $itemId = $this->manualHistoryItemId;
        $this->manualQuoteItems[$itemId] = array_merge($this->manualQuoteItems[$itemId] ?? [], [
            'unit_price' => $reference['unit_price'],
            'iva_rate' => $reference['iva_rate'],
            'currency' => $reference['currency'],
            'delivery_days' => $reference['delivery_days'],
            'payment_terms' => $reference['payment_terms'],
            'not_available' => false,
        ]);
        $this->manualQuoteSupplierId = $reference['supplier_id'];
        $this->applyManualSupplierDefaults((int) $reference['supplier_id'], false);
        $this->closeManualPurchaseHistory();
    }

    private function applyManualSupplierDefaults(int $supplierId, bool $overwrite = false): void
    {
        $supplier = Supplier::find($supplierId);
        if (! $supplier) {
            return;
        }

        foreach ($this->manualQuoteItems as $itemId => $item) {
            $this->manualQuoteItems[$itemId]['currency'] = $overwrite || empty($item['currency'])
                ? ($supplier->currency ?: 'MXN')
                : $item['currency'];
            $this->manualQuoteItems[$itemId]['payment_terms'] = $overwrite || empty($item['payment_terms'])
                ? $supplier->default_payment_terms
                : $item['payment_terms'];
        }
    }

    public function getManualQuoteGroupProperty()
    {
        return $this->manualQuoteGroupId
            ? QuotationGroup::with('items.productService')->find($this->manualQuoteGroupId)
            : null;
    }

    public function getManualQuoteSelectableSuppliersProperty()
    {
        return Supplier::query()
            ->where(function ($q) {
                $q->where('approval_status', 'approved')->where('is_active', true);
            })
            ->orWhere('is_external', true)
            ->orderBy('company_name')
            ->get();
    }

    public function saveManualQuote(): void
    {
        if (! $this->ensureManualQuoteAllowed((int) $this->manualQuoteGroupId)) {
            return;
        }

        $rules = array_merge([
            'manualQuoteQuotationDate' => 'required|date',
            'manualQuoteValidityDays' => 'required|integer|min:1|max:365',
            'manualQuoteAttachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], ManualQuoteService::itemRules());

        if (! $this->manualQuoteSupplierId) {
            $rules = array_merge($rules, ManualQuoteService::newSupplierRules());
        }

        $messages = [
            'manualQuoteNewSupplier.email.unique' => 'Ya existe un proveedor registrado con este correo electrónico.',
        ];

        $this->validate($rules, $messages);

        $supplier = $this->resolveManualQuoteSupplier();
        if (! $supplier) {
            return;
        }

        $group = QuotationGroup::with('items')->findOrFail($this->manualQuoteGroupId);

        try {
            $result = app(ManualQuoteService::class)->save(
                $this->requisition,
                $group,
                $supplier,
                $this->manualQuoteItems,
                $this->manualQuoteQuotationDate,
                (int) $this->manualQuoteValidityDays,
                $this->manualQuoteAttachment,
                Auth::id()
            );
        } catch (\Exception $e) {
            session()->flash('error', 'No se pudo guardar la cotización manual: '.$e->getMessage());

            return;
        }

        $this->showManualQuoteModal = false;
        $this->currentStep = $this->determineCurrentStep();
        $this->loadStepData();
        if (! $result['summary']) {
            session()->flash('error', 'Precio conocido capturado, pero no se pudo enviar a autorización: '.$result['award_error']);

            return;
        }
        session()->flash('success', "Compra directa de {$supplier->company_name} enviada a validación presupuestal y autorización.");
    }

    public function manualBudgetBlockedInfo(int $groupId): ?array
    {
        $rfq = Rfq::query()
            ->where('requisition_id', $this->requisition->id)
            ->where('quotation_group_id', $groupId)
            ->active()
            ->latest('id')
            ->with(['budgetBlockedNotice.buyer', 'rfqResponses.requisitionItem', 'requisition.requester', 'requisition.items.costCenter'])
            ->first();

        if (! $rfq || $rfq->source !== 'external') {
            return null;
        }

        $supplierId = app(ManualQuoteService::class)->editableBudgetBlockedSupplierId($rfq);
        if (! $supplierId) {
            return null;
        }

        return [
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplierId,
            'notice' => $rfq->budgetBlockedNotice,
            'reasons' => app(\App\Services\Rfq\RfqAwardService::class)->supplierDiagnostics($rfq, $supplierId)['budget_messages'],
        ];
    }

    public function openManualBudgetNotice(int $groupId): void
    {
        $info = $this->manualBudgetBlockedInfo($groupId);
        if (! $info) {
            session()->flash('error', 'Este grupo ya no está bloqueado únicamente por presupuesto.');

            return;
        }

        $this->manualQuoteGroupId = $groupId;
        $this->manualBudgetNoticeSupplierId = $info['supplier_id'];
        $this->manualBudgetNoticeNote = '';
        $this->showManualBudgetNoticeModal = true;
    }

    public function closeManualBudgetNotice(): void
    {
        $this->showManualBudgetNoticeModal = false;
    }

    public function sendManualBudgetNotice(): void
    {
        $this->validate(['manualBudgetNoticeNote' => ['nullable', 'string', 'max:1000']]);

        $info = $this->manualBudgetBlockedInfo((int) $this->manualQuoteGroupId);
        if (! $info || (int) $info['supplier_id'] !== (int) $this->manualBudgetNoticeSupplierId) {
            session()->flash('error', 'Este grupo ya no está bloqueado únicamente por presupuesto.');

            return;
        }

        try {
            app(BudgetBlockedNoticeService::class)->send(
                Rfq::findOrFail($info['rfq_id']),
                (int) $info['supplier_id'],
                Auth::user(),
                $this->manualBudgetNoticeNote,
            );
        } catch (\DomainException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        $this->showManualBudgetNoticeModal = false;
        session()->flash('success', 'El requisitor fue informado por correo y notificación interna.');
    }

    /**
     * Cargar datos de proveedores desde RFQs existentes (Paso 3)
     */
    public function loadSuppliersData()
    {
        $this->suppliersData = [];

        // Obtener RFQs existentes agrupadas por quotation_group_id
        $rfqs = $this->requisition->rfqs()
            ->active()
            ->with(['suppliers', 'quotationGroup'])
            ->get();

        if ($rfqs->isEmpty()) {
            return;
        }

        // Mapear datos de RFQs existentes
        foreach ($rfqs as $rfq) {
            $this->suppliersData[] = [
                'rfq_id' => $rfq->id,
                'group_id' => $rfq->quotation_group_id,
                'group_name' => $rfq->quotationGroup->name ?? '',
                'supplier_ids' => $rfq->suppliers->pluck('id')->toArray(),
                'response_deadline' => $rfq->response_deadline?->format('Y-m-d') ?? now()->addDays(7)->format('Y-m-d'),
                'notes' => $rfq->message ?? '',
            ];
        }

        Log::info('✅ Datos de proveedores cargados', [
            'count' => count($this->suppliersData),
            'data' => $this->suppliersData,
        ]);
    }

    /**
     * Asegura que toda partida tenga una solicitud de cotización propia o grupal.
     */
    private function createIndividualGroupsForUnassignedItems(): void
    {
        if ($this->isReadOnlyAfterSend) {
            return;
        }

        $unassignedItems = $this->requisition->items()
            ->whereDoesntHave('quotationGroups', fn ($query) => $query->active())
            ->with('productService:id,code,short_name')
            ->get();

        if ($unassignedItems->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($unassignedItems): void {
            foreach ($unassignedItems as $item) {
                $label = $item->productService?->short_name
                    ?: $item->productService?->code
                    ?: $item->description
                    ?: 'Partida '.$item->line_number;

                $group = QuotationGroup::create([
                    'requisition_id' => $this->requisition->id,
                    'name' => 'Individual · '.\Illuminate\Support\Str::limit($label, 220, ''),
                    'notes' => 'Grupo individual creado automáticamente para una partida sin agrupar.',
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
                $group->items()->attach($item->id, ['sort_order' => 0]);
            }
        });

        $this->requisition->unsetRelation('quotationGroups');
        $this->requisition->load('quotationGroups.items');
    }

    private function refreshReadOnlyAfterSend(): void
    {
        $this->isReadOnlyAfterSend = $this->requisition->rfqs()
            ->whereIn('status', ['SENT', 'RESPONSES_RECEIVED', 'RECEIVED', 'EVALUATED'])
            ->exists();

        $this->hasEditableManualBudgetBlockedGroup = false;

        if (! $this->isReadOnlyAfterSend || ! Auth::user()?->hasAnyRole(['buyer', 'superadmin'])) {
            return;
        }

        $this->hasEditableManualBudgetBlockedGroup = $this->requisition->rfqs()
            ->active()
            ->where('source', 'external')
            ->where('status', 'RECEIVED')
            ->get()
            ->contains(fn (Rfq $rfq) => app(ManualQuoteService::class)->editableBudgetBlockedSupplierId($rfq) !== null);
    }

    private function ensureEditableBeforeSend(): bool
    {
        $this->refreshReadOnlyAfterSend();

        if (! $this->isReadOnlyAfterSend) {
            return true;
        }

        session()->flash('error', 'Esta requisición ya tiene solicitudes enviadas y las etapas anteriores están disponibles solo para consulta.');

        return false;
    }

    /** La compra directa es exclusiva del grupo y no depende de otros grupos enviados. */
    private function ensureManualQuoteAllowed(int $groupId): bool
    {
        if (! $this->requisition->validated_at) {
            session()->flash('error', 'Firma la validación técnica antes de capturar un precio conocido.');

            return false;
        }

        $rfq = $this->requisition->rfqs()
            ->where('quotation_group_id', $groupId)
            ->active()
            ->latest('id')
            ->first();

        if ($rfq && $rfq->source !== 'external' && $rfq->status !== 'DRAFT') {
            session()->flash('error', 'Este grupo ya tiene una RFQ enviada y no puede cambiar a compra directa.');

            return false;
        }

        if ($rfq && $rfq->source === 'external' && ! in_array($rfq->status, ['DRAFT', 'RECEIVED'], true)) {
            session()->flash('error', 'Este grupo ya tiene una compra directa en validación o autorizada.');

            return false;
        }

        if ($rfq?->source === 'external' && $rfq->status === 'RECEIVED') {
            if (! Auth::user()?->hasAnyRole(['buyer', 'superadmin']) || ! app(ManualQuoteService::class)->editableBudgetBlockedSupplierId($rfq)) {
                session()->flash('error', 'Esta compra directa sólo puede editarse por Compras o Superadministración cuando está bloqueada únicamente por presupuesto.');

                return false;
            }
        }

        return true;
    }

    /**
     * Renderizar el componente
     */
    public function render()
    {
        return view('livewire.rfq.quotation-wizard');
    }
}
