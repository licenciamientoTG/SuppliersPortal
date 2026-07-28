<?php

namespace App\Livewire\Rfq;

use App\Enum\RequisitionStatus;
use App\Exceptions\Rfq\DuplicateSupplierRfcException;
use App\Exceptions\Rfq\IncompleteValidationException;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Services\Rfq\ManualQuoteService;
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
        if ($this->requisition->quotationGroups()->exists()) {
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
            ->with('items.productService')
            ->get()
            ->toArray();
    }

    /**
     * Completar y guardar el paso 1 (Validación)
     */
    public function completeStep1()
    {
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
        $group = QuotationGroup::with('items')->findOrFail($quotationGroupId);

        $this->manualQuoteGroupId = $group->id;
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
        foreach ($group->items as $item) {
            $this->manualQuoteItems[$item->id] = [
                'not_available' => false,
                'unit_price' => null,
                'iva_rate' => 16,
                'currency' => 'MXN',
                'delivery_days' => null,
                'payment_terms' => null,
                'warranty_terms' => null,
                'brand' => null,
                'model' => null,
                'specifications' => null,
            ];
        }

        $this->manualQuoteQuotationDate = now()->format('Y-m-d');
        $this->manualQuoteValidityDays = 30;
        $this->manualQuoteAttachment = null;
        $this->resetErrorBag();
        $this->showManualQuoteModal = true;
    }

    public function closeManualQuoteModal(): void
    {
        $this->showManualQuoteModal = false;
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
            app(ManualQuoteService::class)->save(
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
        session()->flash('success', "✅ Cotización de {$supplier->company_name} capturada para el grupo {$group->name}.");
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
     * Renderizar el componente
     */
    public function render()
    {
        return view('livewire.rfq.quotation-wizard');
    }
}
