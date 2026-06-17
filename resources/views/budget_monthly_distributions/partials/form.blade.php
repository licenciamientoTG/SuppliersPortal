@php
    $selectedCategoryIds = collect($selectedCategoryIds ?? [])->map(fn ($id) => (int) $id)->values();
    $selectedCedulas = collect($selectedCedulas ?? []);
    $categories = $cedulasByCategory->map(function ($cedulas) {
        $category = $cedulas->first()->expenseCategory;

        return [
            'id' => $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'cedulas' => $cedulas->map(fn ($cedula) => [
                'id' => $cedula->id,
                'name' => $cedula->name,
            ])->values(),
        ];
    })->sortBy(fn ($category) => $category['code'])->values();
@endphp

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="ti ti-table me-2"></i>
            Distribución Mensual por Categoría y Cédula
        </h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-4">
            <div class="row">
                <div class="col-md-6">
                    <strong>Empresa:</strong> {{ $annualBudget->costCenter?->company?->name ?? '—' }}<br>
                    <strong>Centro de Costo:</strong> [{{ $annualBudget->costCenter?->code ?? '—' }}] {{ $annualBudget->costCenter?->name ?? '—' }}
                </div>
                <div class="col-md-6">
                    <strong>Ejercicio Fiscal:</strong> {{ $annualBudget->fiscal_year }}<br>
                    <strong>Monto Total Anual:</strong> <span class="badge bg-success">${{ number_format($annualBudget->total_annual_amount, 2) }}</span>
                </div>
            </div>
        </div>

        <div class="card mb-4 border-primary">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="ti ti-category me-2"></i>
                    Paso 1: Seleccione las categorías a presupuestar
                </h6>
            </div>
            <div class="card-body">
                <label for="categorySelector" class="form-label">
                    Categorías de gasto <span class="text-danger">*</span>
                </label>
                <select id="categorySelector" class="form-select" multiple="multiple" style="width: 100%;">
                    @foreach ($categories as $category)
                        <option
                            value="{{ $category['id'] }}"
                            data-code="{{ $category['code'] }}"
                            data-name="{{ $category['name'] }}"
                            @if ($selectedCategoryIds->contains($category['id'])) selected @endif>
                            [{{ $category['code'] }}] {{ $category['name'] }}
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">Cada categoría agrupa sus cédulas hijas. Los montos se capturan en las cédulas y la categoría solo resume.</small>
            </div>
        </div>

        <div id="categoryPanels"></div>

        <div id="validationAlert" class="alert alert-warning mt-3 d-none">
            <i class="ti ti-alert-triangle me-2"></i>
            <strong>Advertencia:</strong> La suma de las distribuciones mensuales debe coincidir con el monto total anual.
            <div class="mt-2">
                <strong>Monto Total Anual:</strong> ${{ number_format($annualBudget->total_annual_amount, 2) }}<br>
                <strong>Suma de Distribuciones:</strong> <span id="sumDistributions">$0.00</span><br>
                <strong>Diferencia:</strong> <span id="difference" class="text-danger">$0.00</span>
            </div>
        </div>

        <div class="budget-summary-bar mt-3">
            <div class="budget-summary-card">
                <span class="budget-summary-label">Monto anual</span>
                <strong id="summaryBudgetTotal">${{ number_format($annualBudget->total_annual_amount, 2) }}</strong>
            </div>
            <div class="budget-summary-card">
                <span class="budget-summary-label">Distribuido</span>
                <strong id="sumDistributionsInline">$0.00</strong>
            </div>
            <div class="budget-summary-card">
                <span class="budget-summary-label">Diferencia</span>
                <strong id="differenceInline" class="text-danger">$0.00</strong>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const budgetTotalAmount = {{ (float) $annualBudget->total_annual_amount }};
    const isEdit = {{ $isEdit ? 'true' : 'false' }};
    const categories = @json($categories->values());
    const existingDistributions = @json($distributions);
    const monthLabels = @json(array_map(fn ($month) => \App\Models\BudgetMonthlyDistribution::make(['month' => $month])->month_label, range(1, 12)));

    const categorySelector = $('#categorySelector');
    const categoryPanels = document.getElementById('categoryPanels');
    let globalIndex = 0;

    categorySelector.select2({
        theme: 'bootstrap-5',
        placeholder: 'Seleccione categorías...',
        allowClear: true,
        closeOnSelect: false,
    });

    function formatMoney(value) {
        return '$' + Number(value || 0).toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function parseMoney(value) {
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : 0;
        }

        if (!value) {
            return 0;
        }

        const normalized = String(value)
            .replace(/[^\d.,-]/g, '')
            .replace(/,/g, '');

        const parsed = Number.parseFloat(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function formatMoneyInputValue(value) {
        return Number(value || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function updateMoneyInputDisplay(input) {
        input.value = formatMoneyInputValue(parseMoney(input.value));
    }

    function syncHiddenAmount(input) {
        const hiddenSelector = input.dataset.hiddenInput;
        if (!hiddenSelector) {
            return;
        }

        const hiddenInput = document.querySelector(hiddenSelector);
        if (hiddenInput) {
            hiddenInput.value = parseMoney(input.value).toFixed(2);
        }
    }

    function selectedCategories() {
        const selectedIds = new Set((categorySelector.val() || []).map(Number));
        return categories.filter(category => selectedIds.has(category.id));
    }

    function getCedulaMonthData(cedulaId, month) {
        return existingDistributions?.[cedulaId]?.[month] ?? null;
    }

    function renderCedulaRow(category, cedula) {
        let row = `<tr data-cedula-id="${cedula.id}" data-category-id="${category.id}">`;
        row += `<td class="sticky-column bg-light"><small class="text-muted d-block">[${category.code}] ${category.name}</small><strong>${cedula.name}</strong></td>`;

        for (let month = 1; month <= 12; month++) {
            const data = getCedulaMonthData(cedula.id, month);
            const distributionId = data?.id ?? null;
            const assignedAmount = data?.assigned_amount ?? 0;
            const consumedAmount = data?.consumed_amount ?? 0;
            const committedAmount = data?.committed_amount ?? 0;
            const minimumRequired = consumedAmount + committedAmount;

            row += '<td class="text-center p-1">';
            if (isEdit && distributionId) {
                row += `<input type="hidden" name="distributions[${globalIndex}][id]" value="${distributionId}">`;
                row += `<input type="hidden" id="distribution-hidden-${distributionId}" name="distributions[${globalIndex}][assigned_amount]" value="${Number(assignedAmount).toFixed(2)}">`;
                row += `<div class="money-input-wrap"><span class="money-prefix">$</span><input type="text" inputmode="decimal" class="form-control form-control-sm text-end distribution-input money-input" data-hidden-input="#distribution-hidden-${distributionId}" data-category="${category.id}" data-cedula="${cedula.id}" data-month="${month}" data-initial-value="${Number(assignedAmount).toFixed(2)}" value="${formatMoneyInputValue(assignedAmount)}" data-min-amount="${minimumRequired.toFixed(2)}" required></div>`;
                if (minimumRequired > 0) {
                    row += `<small class="text-warning d-block"><i class="ti ti-lock"></i> Min: ${formatMoney(minimumRequired)}</small>`;
                }
                globalIndex++;
            } else {
                row += `<input type="hidden" id="distribution-hidden-${cedula.id}-${month}" name="distributions[${cedula.id}][${month}]" value="${Number(assignedAmount).toFixed(2)}">`;
                row += `<div class="money-input-wrap"><span class="money-prefix">$</span><input type="text" inputmode="decimal" class="form-control form-control-sm text-end distribution-input money-input" data-hidden-input="#distribution-hidden-${cedula.id}-${month}" data-category="${category.id}" data-cedula="${cedula.id}" data-month="${month}" data-initial-value="${Number(assignedAmount).toFixed(2)}" value="${formatMoneyInputValue(assignedAmount)}" data-min-amount="0.00" required></div>`;
            }
            row += '</td>';
        }

        row += `<td class="text-center bg-light"><strong class="cedula-total" data-cedula="${cedula.id}">$0.00</strong></td>`;
        row += '</tr>';
        return row;
    }

    function renderCategoryPanel(category, index) {
        const collapseId = `category-collapse-${category.id}`;
        let html = `
            <div class="card mb-3 category-card" data-category-id="${category.id}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <button class="btn btn-link text-decoration-none p-0 fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="${index === 0 ? 'true' : 'false'}">
                        [${category.code}] ${category.name}
                    </button>
                    <div class="text-end">
                        <small class="text-muted d-block">Total categoría</small>
                        <strong class="category-total" data-category="${category.id}">$0.00</strong>
                    </div>
                </div>
                <div id="${collapseId}" class="collapse ${index === 0 ? 'show' : ''}">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="sticky-column" style="min-width: 250px;">Cédula</th>
                                        ${monthLabels.map(label => `<th class="text-center text-nowrap">${label}</th>`).join('')}
                                        <th class="text-center bg-secondary text-white">TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${category.cedulas.map(cedula => renderCedulaRow(category, cedula)).join('')}
                                </tbody>
                                <tfoot class="table-secondary">
                                    <tr>
                                        <th class="sticky-column">Total categoría por mes</th>
                                        ${monthLabels.map((_, idx) => `<th class="text-center"><strong class="category-month-total" data-category="${category.id}" data-month="${idx + 1}">$0.00</strong></th>`).join('')}
                                        <th class="text-center bg-dark text-white"><strong class="category-total" data-category="${category.id}">$0.00</strong></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;

        return html;
    }

    function renderPanels() {
        globalIndex = 0;
        const selected = selectedCategories();
        categoryPanels.innerHTML = selected.length === 0
            ? '<div class="alert alert-secondary text-center"><i class="ti ti-info-circle me-2"></i>Seleccione al menos una categoría para capturar el presupuesto.</div>'
            : selected.map((category, index) => renderCategoryPanel(category, index)).join('');

        attachInputEvents();
        calculateTotals();
    }

    function attachInputEvents() {
        document.querySelectorAll('.distribution-input').forEach(input => {
            input.addEventListener('focus', () => {
                input.value = parseMoney(input.value) === 0 ? '' : parseMoney(input.value).toFixed(2);
            });

            input.addEventListener('input', () => {
                syncHiddenAmount(input);
                calculateTotals();
            });

            input.addEventListener('blur', () => {
                const minAmount = parseMoney(input.dataset.minAmount || 0);
                let currentValue = parseMoney(input.value);

                if (currentValue < minAmount) {
                    currentValue = minAmount;
                    input.value = currentValue.toFixed(2);
                }

                syncHiddenAmount(input);
                updateMoneyInputDisplay(input);
                calculateTotals();
            });

            syncHiddenAmount(input);
            updateMoneyInputDisplay(input);
        });
    }

    function calculateTotals() {
        let grandTotal = 0;

        selectedCategories().forEach(category => {
            let categoryTotal = 0;

            category.cedulas.forEach(cedula => {
                let cedulaTotal = 0;

                for (let month = 1; month <= 12; month++) {
                    const input = document.querySelector(`.distribution-input[data-cedula="${cedula.id}"][data-month="${month}"]`);
                    const value = parseMoney(input?.value || 0);
                    cedulaTotal += value;

                    const monthTotalEl = document.querySelector(`.category-month-total[data-category="${category.id}"][data-month="${month}"]`);
                    if (monthTotalEl) {
                        const current = Number(monthTotalEl.dataset.raw || 0) + value;
                        monthTotalEl.dataset.raw = current;
                    }
                }

                const cedulaTotalEl = document.querySelector(`.cedula-total[data-cedula="${cedula.id}"]`);
                if (cedulaTotalEl) {
                    cedulaTotalEl.textContent = formatMoney(cedulaTotal);
                }

                categoryTotal += cedulaTotal;
            });

            for (let month = 1; month <= 12; month++) {
                const monthTotalEl = document.querySelector(`.category-month-total[data-category="${category.id}"][data-month="${month}"]`);
                if (monthTotalEl) {
                    monthTotalEl.textContent = formatMoney(monthTotalEl.dataset.raw || 0);
                    monthTotalEl.dataset.raw = 0;
                }
            }

            document.querySelectorAll(`.category-total[data-category="${category.id}"]`).forEach(el => {
                el.textContent = formatMoney(categoryTotal);
            });

            grandTotal += categoryTotal;
        });

        const difference = Math.abs(grandTotal - budgetTotalAmount);
        document.getElementById('sumDistributions').textContent = formatMoney(grandTotal);
        document.getElementById('difference').textContent = formatMoney(difference);
        document.getElementById('sumDistributionsInline').textContent = formatMoney(grandTotal);
        document.getElementById('differenceInline').textContent = formatMoney(difference);
        document.getElementById('validationAlert').classList.toggle('d-none', difference <= 0.01);
    }

    categorySelector.on('change', renderPanels);

    document.getElementById('formDistributions')?.addEventListener('submit', function(e) {
        if ((categorySelector.val() || []).length === 0) {
            e.preventDefault();
            Swal.fire({
                title: 'Sin categorías',
                text: 'Debe seleccionar al menos una categoría para el presupuesto.',
                icon: 'error',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        document.querySelectorAll('.distribution-input').forEach(syncHiddenAmount);

        const total = parseMoney(document.getElementById('sumDistributions').textContent);
        const difference = Math.abs(total - budgetTotalAmount);

        if (difference > 0.01) {
            e.preventDefault();
            Swal.fire({
                title: 'Distribución incompleta',
                html: `La suma capturada es <strong>${formatMoney(total)}</strong> y debe coincidir con <strong>${formatMoney(budgetTotalAmount)}</strong>.`,
                icon: 'error',
                confirmButtonText: 'Entendido'
            });
        }
    });

    renderPanels();
});
</script>

<style>
.sticky-column {
    position: sticky;
    left: 0;
    background: #f8f9fa;
    z-index: 2;
}

.category-card .table td,
.category-card .table th {
    vertical-align: middle;
}

.distribution-input {
    min-width: 96px;
    padding-left: 1.5rem;
    font-variant-numeric: tabular-nums;
}

.money-input-wrap {
    position: relative;
}

.money-prefix {
    position: absolute;
    left: 0.55rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    font-size: 0.85rem;
    z-index: 3;
}

.budget-summary-bar {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
}

.budget-summary-card {
    background: linear-gradient(180deg, #f8fbff 0%, #eef4ff 100%);
    border: 1px solid #d8e5ff;
    border-radius: 0.75rem;
    padding: 0.85rem 1rem;
}

.budget-summary-label {
    display: block;
    color: #6c757d;
    font-size: 0.8rem;
    margin-bottom: 0.2rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

@media (max-width: 992px) {
    .budget-summary-bar {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush
