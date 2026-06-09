@php
    $year = old('fiscal_year', $requisition->fiscal_year ?? date('Y'));
    $currency = old('currency_code', $requisition->currency_code ?? 'MXN');
    $items = old('items', isset($requisition) && $requisition->exists ? $requisition->items->toArray() : []);
    $requiredRaw = old('required_date', optional($requisition->required_date)->format('Y-m-d'));
@endphp

@push('styles')
    <style>
        #itemsTable input.form-control-sm,
        #itemsTable select.form-select-sm,
        #itemsTable textarea.form-control-sm {
            padding-top: 0.25rem;
            padding-bottom: 0.25rem;
        }

        #itemsTable .d-flex.flex-column.gap-1>* {
            flex: 1;
        }

        /* Fuerza que las filas de partidas se comporten como tabla */
        #itemsTable tbody tr.req-item-row {
            display: table-row !important;
        }

        #itemsTable tbody tr.req-item-row>td {
            display: table-cell !important;
            vertical-align: middle;
        }
    </style>
@endpush

<div class="row g-3">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Información General</h3>
        </div>
        <div class="card-body">
            <div class="row g-2">
                {{-- PRIMERA FILA --}}
                @if (!empty($requisition->folio))
                    <div class="col-md-1">
                        <label class="form-label form-label-sm">Folio</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">
                                <i class="ti ti-file-text"></i>
                            </span>
                            <input type="text" class="form-control form-control-sm" value="{{ $requisition->folio }}"
                                readonly>
                        </div>
                    </div>
                @endif

                <div class="col-md-{{ !empty($requisition->folio) ? '1' : '2' }}">
                    <label for="fiscal_year" class="form-label form-label-sm">
                        Año fiscal <span class="text-danger">*</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">
                            <i class="ti ti-calendar"></i>
                        </span>
                        <input type="number" min="2000" max="2100"
                            class="form-control form-control-sm @error('fiscal_year') is-invalid @enderror"
                            id="fiscal_year" name="fiscal_year" value="{{ $year }}">
                        @error('fiscal_year')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-2">
                    <label for="required_date" class="form-label form-label-sm">
                        Fecha requerida <span class="text-danger">*</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">
                            <i class="ti ti-clock"></i>
                        </span>
                        <input type="date" id="required_date" name="required_date"
                            class="form-control form-control-sm @error('required_date') is-invalid @enderror"
                            value="{{ $requiredRaw }}">
                        @error('required_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-2">
                    <label for="amount_requested" class="form-label form-label-sm">Monto</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">
                            <i class="ti ti-currency-dollar"></i>
                        </span>
                        <input type="text" id="amount_requested" class="form-control form-control-sm bg-light"
                            value="{{ number_format($requisition->amount_requested ?? 0, 2) }}" readonly>
                    </div>
                </div>

                <div class="col-md-2">
                    <label for="currency_code" class="form-label form-label-sm">
                        Moneda <span class="text-danger">*</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">
                            <i class="ti ti-coin"></i>
                        </span>
                        <select id="currency_code" name="currency_code"
                            class="form-select-sm @error('currency_code') is-invalid @enderror form-select" required>
                            @foreach ($currencies as $code => $label)
                                <option value="{{ $code }}" {{ $currency === $code ? 'selected' : '' }}>
                                    {{ $code }}
                                </option>
                            @endforeach
                        </select>
                        @error('currency_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-{{ !empty($requisition->folio) ? '4' : '4' }}">
                    <label for="company_id" class="form-label form-label-sm">Compañía <span
                            class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="ti ti-building"></i></span>
                        <select id="company_id" name="company_id"
                            class="form-select-sm @error('company_id') is-invalid @enderror form-select" required
                            data-url-costcenters="{{ route('api.cost-centers.by-company', ['company' => '__CID__']) }}"
                            data-selected-cc="{{ old('cost_center_id', $requisition->primaryCostCenter()?->id ?? '') }}">
                            <option value="">-- Selecciona --</option>
                            @foreach ($companies as $c)
                                <option value="{{ $c->id }}"
                                    {{ (int) old('company_id', $selectedCompanyId) === (int) $c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('company_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <small class="text-muted">Sólo compañías vinculadas al usuario actual.</small>
                </div>

                {{-- SEGUNDA FILA --}}
                <div class="col-md-3">
                    <label for="cost_center_id" class="form-label form-label-sm">Centro de costo <span
                            class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="ti ti-hierarchy-2"></i></span>
                        <select id="cost_center_id" name="cost_center_id"
                            class="form-select-sm @error('cost_center_id') is-invalid @enderror form-select" required>
                            <option value="">-- Selecciona --</option>
                        </select>
                        @error('cost_center_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <small class="text-muted">Sólo centros de costo de la compañía seleccionada.</small>
                </div>

                <div class="col-md-2">
                    <label for="department_id" class="form-label form-label-sm">
                        Departamento <span class="text-danger">*</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">
                            <i class="ti ti-users"></i>
                        </span>
                        <select id="department_id" name="department_id"
                            class="form-select-sm @error('department_id') is-invalid @enderror form-select" required>
                            <option value="">-- Selecciona --</option>
                            @foreach ($departments as $dep)
                                <option value="{{ $dep->id }}"
                                    {{ (int) old('department_id', $requisition->department_id ?? 0) === (int) $dep->id ? 'selected' : '' }}>
                                    {{ $dep->name }} @if (!$dep->is_active)
                                        (Inactivo)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('department_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-3">
                    <label for="receiving_location_id" class="form-label form-label-sm">
                        Ubicación de recepción <span class="text-danger">*</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="ti ti-map-pin"></i></span>
                        <select id="receiving_location_id" name="receiving_location_id"
                            class="form-select-sm @error('receiving_location_id') is-invalid @enderror form-select" required>
                            <option value="">-- Selecciona --</option>
                            @foreach ($receivingLocations as $loc)
                                <option value="{{ $loc->id }}"
                                    {{ (int) old('receiving_location_id', $requisition->receiving_location_id ?? 0) === (int) $loc->id ? 'selected' : '' }}>
                                    [{{ $loc->code }}] {{ $loc->name }}{{ $loc->city ? ' — ' . $loc->city : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('receiving_location_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-3">
                    <label for="description" class="form-label form-label-sm">
                        Descripción <span class="text-danger">*</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">
                            <i class="ti ti-file-description"></i>
                        </span>
                        <input type="text" id="description" name="description"
                            class="form-control form-control-sm @error('description') is-invalid @enderror"
                            value="{{ old('description', $requisition->description ?? '') }}"
                            placeholder="Motivo de la requisición" maxlength="255">
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Tabla de partidas --}}
<div class="table-responsive">
    <table class="table-bordered table align-middle" id="itemsTable">
        <colgroup>
            <col style="width:50px;">  {{-- # --}}
            <col style="width:180px;"> {{-- Categoría / Código --}}
            <col>                      {{-- Descripción (Proveedor/Notas) --}}
            <col style="width:160px;"> {{-- Cantidad / Unidad --}}
            <col style="width:120px;"> {{-- Precio unit. --}}
            <col style="width:150px;"> {{-- IVA % --}}
            <col style="width:120px;"> {{-- Subtotal --}}
            <col style="width:120px;"> {{-- Total --}}
            <col style="width:50px;">  {{-- Acción --}}
        </colgroup>
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Categoría / Código</th>
                <th>Descripción (Proveedor/Notas)</th>
                <th>Cantidad / Unidad</th>
                <th>Precio unit.</th>
                <th>IVA %</th>
                <th class="text-end">Subtotal</th>
                <th class="text-end">Total</th>
                <th class="text-center">–</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $idx => $it)
                @include('requisitions._items_row_data', [
                    'idx' => $idx,
                    'it' => $it,
                    'unitOptions' => $unitOptions,
                    'taxes' => $taxes,
                ])
            @empty
                {{-- Sin filas: se insertará una por JS usando el template _items_row.blade.php --}}
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="9" class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAddRow">
                        <i class="ti ti-plus"></i> Agregar partida
                    </button>
                </td>
            </tr>
        </tfoot>
    </table>
</div>

{{-- Template inerte para nuevas filas --}}
<template id="rowTemplate">
    @include('requisitions._items_row')
</template>

@push('scripts')
    <script>
        $(function() {
            const $company = $('#company_id');
            const $cc = $('#cost_center_id');

            function fillCostCenters(list) {
                const placeholder = '<option value="">-- Selecciona --</option>';
                $cc.empty().append(placeholder);

                list.forEach(function(row) {
                    $cc.append($('<option>', {
                        value: row.id,
                        text: row.name
                    }));
                });

                // recuperar CC deseado (editar)
                const wanted = $company.data('selectedCc') ?? $company.attr('data-selected-cc') ?? '';
                if (wanted) $cc.val(String(wanted));

                $cc.trigger('change');
            }

            function loadCostCenters(companyId) {
                if (!companyId) {
                    fillCostCenters([]); // limpiar
                    return;
                }

                $cc.prop('disabled', true)
                    .empty()
                    .append('<option value="">Cargando...</option>');

                const tmpl = $company.data('urlCostcenters') || $company.attr('data-url-costcenters');
                const url = String(tmpl).replace('__CID__', companyId);

                $.getJSON(url)
                    .done(function(data) {
                        const list = Array.isArray(data) ? data : [];
                        fillCostCenters(list);

                        if (list.length === 0) {
                            // ⚠️ Mostrar alerta si no hay centros de costo
                            Swal.fire({
                                icon: 'warning',
                                title: 'Sin Centros de Costo',
                                text: 'La compañía seleccionada no tiene centros de costo asociados.',
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#1a4b96'
                            });
                        }
                    })
                    .fail(function() {
                        fillCostCenters([]);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudieron cargar los centros de costo. Inténtalo nuevamente.',
                            confirmButtonText: 'Cerrar',
                            confirmButtonColor: '#1a4b96'
                        });
                    })
                    .always(function() {
                        $cc.prop('disabled', false);
                    });
            }

            // change compañía → recarga CC
            $company.on('change', function() {
                const companyId = $(this).val();
                // limpiar selección guardada
                $company.removeData('selectedCc').attr('data-selected-cc', '');
                loadCostCenters(companyId);
            });

            // on load: si hay compañía, sincroniza CC
            const initialCompany = $company.val();
            if (initialCompany) loadCostCenters(initialCompany);
        });

        // ======= Tu IIFE de partidas tal cual =======
        (function() {
            // número de filas presentes al cargar
            let rowIndex = document.querySelectorAll('#itemsTable tbody tr').length;

            if (rowIndex === 0) addRow();
            else {
                recalcAll();
                normalizeLineNumbers();
            }

            const table = document.getElementById('itemsTable');

            document.getElementById('btnAddRow').addEventListener('click', addRow);

            table.addEventListener('input', function(e) {
                if (e.target.closest('.qty-input') || e.target.closest('.price-input') || e.target.closest(
                        '.tax-input')) {
                    const tr = e.target.closest('tr');
                    recalcRow(tr);
                    recalcHeader();
                }
                if (e.target.closest('.line-number')) normalizeLineNumbers();
            });

            table.addEventListener('change', function(e) {
                const sel = e.target.closest('.tax-select');
                if (sel) {
                    const opt = sel.selectedOptions && sel.selectedOptions[0];
                    const rateStr = opt ? (opt.dataset.rate ?? '') : '';
                    const target = sel.getAttribute('data-target-rate');
                    if (target) {
                        const inp = document.querySelector(target);
                        if (inp) {
                            const rate = rateStr === '' ? '' : parseFloat(rateStr);
                            if (!Number.isNaN(rate)) inp.value = rate;
                            const tr = inp.closest('tr');
                            if (tr) {
                                recalcRow(tr);
                                recalcHeader();
                            }
                        }
                    }
                }
            });

            table.addEventListener('click', function(e) {
                const btn = e.target.closest('.js-remove-row');
                if (!btn) return;
                const tr = btn.closest('tr');
                tr.remove();
                normalizeLineNumbers();
                recalcAll();
            });

            function addRow() {
                const tbody = document.querySelector('#itemsTable tbody');
                const nextLine = tbody.querySelectorAll('tr').length + 1; // consecutivo visible
                rowIndex++; // índice único para names[]

                const html = document.getElementById('rowTemplate').innerHTML
                    .replaceAll('__INDEX__', rowIndex)
                    .replaceAll('__LINE__', nextLine);

                tbody.insertAdjacentHTML('beforeend', html);
                normalizeLineNumbers();
                recalcAll();
            }

            function recalcRow(tr) {
                const qty = parseFloat(tr.querySelector('.qty-input')?.value || 0);
                const price = parseFloat(tr.querySelector('.price-input')?.value || 0);
                const tax = parseFloat(tr.querySelector('.tax-input')?.value || 0);
                const subtotal = qty * price;
                const total = subtotal + (subtotal * (tax / 100));
                tr.querySelector('.line-subtotal').textContent = subtotal.toFixed(2);
                tr.querySelector('.line-total').textContent = total.toFixed(2);
            }

            function recalcAll() {
                document.querySelectorAll('#itemsTable tbody tr').forEach(recalcRow);
                recalcHeader();
            }

            function recalcHeader() {
                let sum = 0;
                document.querySelectorAll('#itemsTable tbody .line-subtotal').forEach(function(el) {
                    sum += parseFloat(el.textContent || 0);
                });
                const amount = document.getElementById('amount_requested');
                if (amount) amount.value = sum.toFixed(2);
            }

            function normalizeLineNumbers() {
                let n = 1;
                document.querySelectorAll('#itemsTable tbody .line-number').forEach(function(inp) {
                    if (!inp.value || isNaN(parseInt(inp.value, 10))) inp.value = n;
                    n++;
                });
            }
        })();

        (function() {
            // Inicializa Select2 para proveedor sugerido dentro de un contenedor (document o fila nueva)
            function initVendorSelect(context) {
                const $ctx = context ? $(context) : $(document);
                $ctx.find('.vendor-select').each(function() {
                    const $el = $(this);
                    // Evita doble init
                    if ($el.data('select2')) return;

                    const ajaxUrl = $el.data('ajax-url');
                    const placeholder = $el.data('placeholder') || 'Proveedor sugerido (opcional)';

                    $el.select2({
                        theme: 'bootstrap-5', // si usas el theme, si no omite esta línea
                        width: 'resolve',
                        placeholder: placeholder,
                        allowClear: true, // 👈 permite dejarlo vacío
                        ajax: {
                            url: ajaxUrl,
                            dataType: 'json',
                            delay: 250,
                            data: function(params) {
                                return {
                                    q: params.term || '',
                                    page: params.page || 1
                                };
                            },
                            processResults: function(data, params) {
                                params.page = params.page || 1;
                                return {
                                    results: data.results || [],
                                    pagination: {
                                        more: data.pagination && data.pagination.more
                                    }
                                };
                            },
                            cache: true
                        },
                        // Mínimo de caracteres antes de buscar (ajusta si quieres)
                        minimumInputLength: 1,
                    });
                });
            }

            // Al cargar la página, inicializa los select existentes
            $(document).ready(function() {
                initVendorSelect(document);
            });


            // Si en tu código de agregar fila usas addRow(), llama initVendorSelect en la nueva fila:
            // Busca tu función addRow y añade esta línea al final.
            const _oldAddRow = window.addRow;
            window.addRow = function() {
                // ejecuta tu addRow original
                const res = _oldAddRow ? _oldAddRow() : undefined;

                // La última fila insertada:
                const $lastRow = $('#itemsTable tbody tr').last();
                initVendorSelect($lastRow);
                return res;
            };

        })();
    </script>
@endpush
