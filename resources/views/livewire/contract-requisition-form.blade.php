<div
    x-data="{ showOcdConfirm: false }"
    x-on:toast.window="
        const t = $event.detail[0] ?? $event.detail;
        Swal.fire({
            toast: true, position: 'top-end', showConfirmButton: false,
            timer: 4500, timerProgressBar: true,
            icon: t.type || 'info',
            title: t.message || ''
        });
    "
>

{{-- ─── Cabecera ─────────────────────────────────────────────────── --}}
<div class="card mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="ti ti-file-invoice fs-5"></i>
        <span class="fw-semibold">Requisición por Contrato</span>
    </div>
    <div class="card-body py-3">
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Empresa <span class="text-danger">*</span></label>
                <select wire:model.live="company_id" class="form-select form-select-sm">
                    <option value="">Seleccionar...</option>
                    @foreach($companies as $co)
                        <option value="{{ $co->id }}">{{ $co->name }}</option>
                    @endforeach
                </select>
                @error('company_id')<div class="text-danger" style="font-size:.75rem">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">Fecha requerida <span class="text-danger">*</span></label>
                <input type="date" wire:model="required_date" class="form-control form-control-sm">
                @error('required_date')<div class="text-danger" style="font-size:.75rem">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label form-label-sm mb-1">Ubicación de recepción <span class="text-danger">*</span></label>
                <select wire:model="receiving_location_id" class="form-select form-select-sm">
                    <option value="">Seleccionar...</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </select>
                @error('receiving_location_id')<div class="text-danger" style="font-size:.75rem">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Notas generales</label>
                <input type="text" wire:model="notes" class="form-control form-control-sm" placeholder="Opcional">
            </div>
        </div>
    </div>
</div>

{{-- ─── Agregar partida ──────────────────────────────────────────── --}}
<div class="card mb-3">
    <div class="card-header py-2">
        <span class="fw-semibold small"><i class="ti ti-circle-plus me-1"></i>Agregar partida</span>
    </div>
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">

            {{-- ── Fila 1: Tipo de compra, CC, Contrato, Producto ── --}}
            <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">Tipo de compra <span class="text-danger">*</span></label>
                <select id="cr_purchase_type" class="form-select form-select-sm">
                    <option value="">Seleccionar...</option>
                    @foreach(\App\Enum\PurchaseType::values() as $pt)
                        <option value="{{ $pt }}">{{ $pt }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Centro de costo <span class="text-danger">*</span></label>
                <select id="cr_cost_center_id" class="form-select form-select-sm" disabled>
                    <option value="">Selecciona tipo de compra...</option>
                </select>
                <div id="cr_cc_help" class="form-text" style="font-size:.72rem"></div>
            </div>

            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Contrato <span class="text-danger">*</span></label>
                <select wire:model.live="newItem.contract_id" class="form-select form-select-sm"
                    @if(!$company_id) disabled @endif>
                    <option value="">{{ $company_id ? 'Seleccionar...' : 'Primero selecciona empresa' }}</option>
                    @foreach($eligibleContracts as $c)
                        <option value="{{ $c->id }}">{{ $c->folio }} — {{ $c->supplier->company_name }}</option>
                    @endforeach
                </select>
                @error('newItem.contract_id')<div class="text-danger" style="font-size:.75rem">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label form-label-sm mb-1">Producto / Servicio <span class="text-danger">*</span></label>
                <select wire:model.live="newItem.contract_product_id" class="form-select form-select-sm"
                    @if(!$newItem['contract_id']) disabled @endif>
                    <option value="">Seleccionar producto...</option>
                    @foreach($newItemContractProducts as $cp)
                        <option value="{{ $cp->id }}">{{ $cp->product->name ?? "Producto #{$cp->product_service_id}" }}</option>
                    @endforeach
                </select>
                @error('newItem.contract_product_id')<div class="text-danger" style="font-size:.75rem">{{ $message }}</div>@enderror
            </div>

            {{-- ── Fila 2: Cat. gasto, Subcategoría, Cantidad, Notas ── --}}
            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Categoría de gasto <span class="text-danger">*</span></label>
                <select id="cr_expense_category" class="form-select form-select-sm" disabled>
                    <option value="">Selecciona un centro de costo...</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Subcategoría presupuestal <span class="text-danger">*</span></label>
                <select id="cr_budget_cedula" class="form-select form-select-sm" disabled>
                    <option value="">Selecciona una categoría...</option>
                </select>
            </div>

            <div class="col-md-1">
                <label class="form-label form-label-sm mb-1">Cantidad <span class="text-danger">*</span></label>
                <input type="number" wire:model="newItem.quantity" step="0.001" min="0.001"
                    class="form-control form-control-sm" placeholder="1">
                @error('newItem.quantity')<div class="text-danger" style="font-size:.75rem">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Notas de partida</label>
                <input type="text" wire:model="newItem.notes" class="form-control form-control-sm" placeholder="Opcional">
            </div>

            {{-- Precio snapshot + botón --}}
            <div class="col-12 d-flex align-items-center gap-3 pt-1">
                @if($newItemSnapshotPrice)
                <span class="badge bg-light text-dark border">
                    Precio contrato:&nbsp;<strong>{{ number_format($newItemSnapshotPrice, 2) }} {{ $newItemCurrency }}</strong>
                </span>
                @endif

                <button type="button" id="cr_btn_add_item" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-plus me-1"></i>Agregar partida
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ─── Tabla de partidas + Resumen ──────────────────────────────── --}}
@if(count($items) > 0)
<div class="card mb-3">
    <div class="card-header py-2">
        <span class="fw-semibold small"><i class="ti ti-list me-1"></i>Partidas ({{ count($items) }})</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Contrato</th>
                    <th>Proveedor</th>
                    <th>Producto / Servicio</th>
                    <th class="text-end">Cant.</th>
                    <th class="text-end">Precio unit.</th>
                    <th>C. Costo</th>
                    <th>Cat. Gasto</th>
                    <th>Subcategoría</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $index => $item)
                <tr class="{{ $item['expiry_warning'] ? 'table-warning' : '' }}">
                    <td class="text-muted">{{ $index + 1 }}</td>
                    <td>
                        {{ $item['contract_folio'] }}
                        @if($item['expiry_warning'])
                            <br><span class="badge bg-warning text-dark" style="font-size:.7rem">
                                <i class="ti ti-clock-x"></i> Vence {{ $item['expiry_warning'] }}
                            </span>
                        @endif
                    </td>
                    <td>{{ $item['supplier_name'] }}</td>
                    <td>{{ $item['product_name'] }}</td>
                    <td class="text-end text-nowrap">{{ number_format($item['quantity'], 3, '.', '') }} {{ $item['unit_of_measure'] }}</td>
                    <td class="text-end text-nowrap">{{ number_format($item['unit_price'], 2) }}&nbsp;{{ $item['currency_code'] }}</td>
                    <td>
                        @if($item['cost_center_name'])
                            <span class="badge bg-secondary-subtle text-secondary border" style="font-size:.7rem">
                                {{ $item['cost_center_name'] }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $item['expense_category_name'] ?? '—' }}</td>
                    <td>{{ $item['budget_cedula_name'] ?? '—' }}</td>
                    <td>
                        <button type="button" wire:click="removeItem({{ $index }})"
                            class="btn btn-sm btn-outline-danger py-0 px-1">
                            <i class="ti ti-trash"></i>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Resumen de totales por moneda --}}
    @php $byCurrency = collect($items)->groupBy('currency_code'); @endphp
    <div class="card-footer bg-light py-2">
        <div class="d-flex flex-wrap gap-4 justify-content-end align-items-start">
            @foreach($byCurrency as $currency => $group)
            @php
                $subtotal = $group->sum(fn($i) => (float)$i['unit_price'] * (float)$i['quantity']);
                $iva      = $subtotal * 0.16;
                $total    = $subtotal + $iva;
            @endphp
            <div class="text-end" style="min-width:180px">
                @if($byCurrency->count() > 1)
                    <div class="fw-semibold text-muted small mb-1">{{ $currency }}</div>
                @endif
                <div class="d-flex justify-content-between gap-3">
                    <span class="text-muted small">Subtotal</span>
                    <span class="small">{{ number_format($subtotal, 2) }} {{ $currency }}</span>
                </div>
                <div class="d-flex justify-content-between gap-3">
                    <span class="text-muted small">IVA 16%</span>
                    <span class="small">{{ number_format($iva, 2) }} {{ $currency }}</span>
                </div>
                <div class="d-flex justify-content-between gap-3 border-top mt-1 pt-1">
                    <span class="fw-bold small">Total</span>
                    <span class="fw-bold small">{{ number_format($total, 2) }} {{ $currency }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ─── Botones de acción ────────────────────────────────────────── --}}
<div class="d-flex gap-2 justify-content-end">
    <button type="button" wire:click="saveDraft" class="btn btn-outline-secondary"
        wire:loading.attr="disabled" wire:target="saveDraft">
        <span wire:loading.remove wire:target="saveDraft"><i class="ti ti-device-floppy me-1"></i>Guardar borrador</span>
        <span wire:loading wire:target="saveDraft"><i class="ti ti-loader-2 me-1"></i>Guardando...</span>
    </button>

    <button type="button" x-on:click="showOcdConfirm = true" class="btn btn-primary">
        <i class="ti ti-file-check me-1"></i>Guardar y crear OCD
    </button>
</div>

{{-- ─── Modal confirmación OCD ───────────────────────────────────── --}}
<div class="modal fade" x-bind:class="showOcdConfirm ? 'show d-block' : ''"
    x-bind:style="showOcdConfirm ? 'background:rgba(0,0,0,.45)' : ''"
    x-on:keydown.escape.window="showOcdConfirm = false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="ti ti-file-check me-1"></i>Confirmar creación de OCD</h6>
                <button type="button" class="btn-close btn-sm" x-on:click="showOcdConfirm = false"></button>
            </div>
            <div class="modal-body">
                @php $supplierCount = collect($items)->pluck('supplier_name')->unique()->count(); @endphp
                <p class="mb-2">Se crearán <strong>{{ $supplierCount }} OCD{{ $supplierCount > 1 ? 's' : '' }}</strong> (una por proveedor):</p>
                <ul class="mb-2" style="font-size:.85rem">
                    @foreach(collect($items)->groupBy('supplier_name') as $supplier => $group)
                    @php
                        $gSub = $group->sum(fn($i) => (float)$i['unit_price'] * (float)$i['quantity']);
                        $gCur = $group->first()['currency_code'];
                    @endphp
                    <li><strong>{{ $supplier }}</strong> — {{ $group->count() }} partida{{ $group->count() > 1 ? 's' : '' }}
                        (subtotal {{ number_format($gSub, 2) }} {{ $gCur }})</li>
                    @endforeach
                </ul>
                <p class="mb-0 text-muted small">Las OCDs quedarán en estado <strong>Pendiente de aprobación</strong>.</p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary"
                    x-on:click="showOcdConfirm = false">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary"
                    x-on:click="showOcdConfirm = false"
                    wire:click="submitAndCreateOCDs"
                    wire:loading.attr="disabled" wire:target="submitAndCreateOCDs">
                    <span wire:loading.remove wire:target="submitAndCreateOCDs"><i class="ti ti-check me-1"></i>Confirmar</span>
                    <span wire:loading wire:target="submitAndCreateOCDs"><i class="ti ti-loader-2 me-1"></i>Procesando...</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif

</div>

@push('scripts')
<script>
$(function () {
    'use strict';

    const ccCatalog = @json($costCenterCatalog);

    // ── Helpers ──────────────────────────────────────────────────────────

    function getLivewireComponent() {
        const el = document.querySelector('[wire\\:id]');
        return el ? Livewire.find(el.getAttribute('wire:id')) : null;
    }

    function getCompanyId() {
        return $('[wire\\:model\\.live="company_id"], [wire\\:model="company_id"]').val() || '';
    }

    // ── 1. Tipo de compra → Centro de Costo ──────────────────────────────

    function renderCostCenters(mode = 'reset') {
        const companyId    = getCompanyId();
        const purchaseType = $('#cr_purchase_type').val() || '';
        const $cc          = $('#cr_cost_center_id');

        $cc.empty();

        if (!companyId || !purchaseType) {
            $cc.prop('disabled', true)
               .append('<option value="">Selecciona tipo de compra...</option>');
            $('#cr_cc_help').text('');
            return;
        }

        const matches = ccCatalog.filter(row =>
            String(row.company_id) === String(companyId) &&
            String(row.purchase_type) === String(purchaseType)
        );

        if (matches.length === 0) {
            $cc.prop('disabled', true)
               .append('<option value="">Sin centros de costo para esta combinación</option>');
            $('#cr_cc_help').text('No tienes centros de costo asignados para este tipo de compra.');
            return;
        }

        $cc.prop('disabled', false)
           .append('<option value="">Seleccionar...</option>');

        matches.forEach(row => {
            const label = row.code ? `[${row.code}] ${row.name}` : row.name;
            $cc.append($('<option>', { value: row.id, text: label,
                'data-name': row.name, 'data-code': row.code }));
        });

        if (matches.length === 1) {
            $cc.val(String(matches[0].id));
            loadExpenseCategories();
        }

        $('#cr_cc_help').text('');
    }

    $(document).on('change', '#cr_purchase_type', function () {
        renderCostCenters('reset');
        resetExpenseCategory();
        resetBudgetCedula();
    });

    // ── 2. Centro de Costo → Categoría de gasto (AJAX) ──────────────────

    function loadExpenseCategories() {
        const ccId     = $('#cr_cost_center_id').val();
        const $catSel  = $('#cr_expense_category');

        resetExpenseCategory();
        resetBudgetCedula();

        if (!ccId) return;

        $catSel.prop('disabled', true)
               .empty()
               .append('<option value="">Cargando...</option>');

        $.ajax({
            url: '{{ route("expense-categories.by-cost-center") }}',
            type: 'GET',
            data: { cost_center_id: ccId },
            dataType: 'json',
            success: function (response) {
                $catSel.empty().append('<option value="">Seleccionar...</option>');

                if (response.success && response.categories?.length > 0) {
                    response.categories.forEach(cat => {
                        $catSel.append($('<option>', {
                            value: cat.id,
                            text: `${cat.code} - ${cat.name}`,
                            'data-name': cat.name
                        }));
                    });
                    $catSel.prop('disabled', false);
                } else {
                    $catSel.append('<option value="">Sin categorías disponibles</option>');
                    Swal.fire('Sin categorías', response.message || 'No hay categorías de gasto para este centro de costo.', 'warning');
                }
            },
            error: function (xhr) {
                $catSel.empty().append('<option value="">Error al cargar</option>');
                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudieron cargar las categorías de gasto.', 'error');
            }
        });
    }

    function resetExpenseCategory() {
        $('#cr_expense_category').empty()
            .append('<option value="">Selecciona un centro de costo...</option>')
            .prop('disabled', true);
    }

    $(document).on('change', '#cr_cost_center_id', function () {
        loadExpenseCategories();
    });

    // ── 3. Categoría → Subcategoría (AJAX) ──────────────────────────────

    function loadBudgetCedulas() {
        const ccId     = $('#cr_cost_center_id').val();
        const catId    = $('#cr_expense_category').val();
        const $cedSel  = $('#cr_budget_cedula');

        resetBudgetCedula();

        if (!ccId || !catId) return;

        $cedSel.prop('disabled', true)
               .empty()
               .append('<option value="">Cargando...</option>');

        $.ajax({
            url: '{{ route("expense-categories.cedulas-by-cost-center") }}',
            type: 'GET',
            data: {
                cost_center_id: ccId,
                expense_category_id: catId,
                fiscal_year: new Date().getFullYear()
            },
            dataType: 'json',
            success: function (response) {
                $cedSel.empty().append('<option value="">Seleccionar...</option>');

                if (response.success && response.cedulas?.length > 0) {
                    response.cedulas.forEach(c => {
                        $cedSel.append($('<option>', { value: c.id, text: c.name }));
                    });
                    $cedSel.prop('disabled', false);

                    if (response.cedulas.length === 1) {
                        $cedSel.val(String(response.cedulas[0].id));
                    }
                } else {
                    $cedSel.append('<option value="">Sin subcategorías configuradas</option>');
                }
            },
            error: function () {
                $cedSel.empty().append('<option value="">Error al cargar</option>');
            }
        });
    }

    function resetBudgetCedula() {
        $('#cr_budget_cedula').empty()
            .append('<option value="">Selecciona una categoría...</option>')
            .prop('disabled', true);
    }

    $(document).on('change', '#cr_expense_category', function () {
        loadBudgetCedulas();
    });

    // ── 4. Botón "Agregar partida" ───────────────────────────────────────

    $(document).on('click', '#cr_btn_add_item', function () {
        const purchaseType   = $('#cr_purchase_type').val();
        const ccId           = $('#cr_cost_center_id').val();
        const ccOpt          = $('#cr_cost_center_id option:selected');
        const ccName         = ccOpt.data('code')
            ? `[${ccOpt.data('code')}] ${ccOpt.data('name')}`
            : (ccOpt.data('name') || '');
        const catId          = $('#cr_expense_category').val();
        const catName        = $('#cr_expense_category option:selected').data('name') || $('#cr_expense_category option:selected').text();
        const cedulaId       = $('#cr_budget_cedula').val();
        const cedulaName     = $('#cr_budget_cedula option:selected').text();

        if (!purchaseType) { Swal.fire('Campo requerido', 'Selecciona el Tipo de Compra.', 'error'); return; }
        if (!ccId)         { Swal.fire('Campo requerido', 'Selecciona el Centro de Costo.', 'error'); return; }
        if (!catId)        { Swal.fire('Campo requerido', 'Selecciona la Categoría de Gasto.', 'error'); return; }
        if (!cedulaId)     { Swal.fire('Campo requerido', 'Selecciona la Subcategoría Presupuestal.', 'error'); return; }

        const wire = getLivewireComponent();
        if (!wire) { Swal.fire('Error', 'No se pudo conectar con el formulario.', 'error'); return; }

        const extra = {
            purchase_type:         purchaseType,
            cost_center_id:        ccId,
            cost_center_name:      ccName,
            expense_category_id:   catId,
            expense_category_name: catName,
            budget_cedula_id:      cedulaId,
            budget_cedula_name:    cedulaName,
        };

        wire.$call('addItem', extra);

        // Limpiar los selects JS tras agregar
        $('#cr_purchase_type').val('');
        renderCostCenters('reset');
        resetExpenseCategory();
        resetBudgetCedula();
    });

    // ── 5. Reinicializar al cambiar empresa (Livewire re-render) ─────────

    Livewire.on('company-changed', () => {
        renderCostCenters('reset');
        resetExpenseCategory();
        resetBudgetCedula();
    });
});
</script>
@endpush
