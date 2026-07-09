@php
    $selectorId = $selectorId ?? 'budget-cedula-selector';
    $selectedIds = collect($selectedIds ?? [])->map(fn ($id) => (int) $id);
    $groupedCedulas = $budgetCedulas
        ->groupBy(fn ($cedula) => $cedula->expenseCategory?->id ?? 0)
        ->map(function ($cedulas) {
            return [
                'category' => $cedulas->first()->expenseCategory,
                'cedulas' => $cedulas->sortBy('name')->values(),
            ];
        })
        ->sortBy(fn ($group) => $group['category']?->code ?? '');
@endphp

<div id="{{ $selectorId }}" class="budget-cedula-selector border rounded">
    <div class="p-3 border-bottom bg-light">
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div>
                <label class="form-label mb-1">Subcuentas</label>
                <div class="small text-muted">Selecciona una cuenta para incluir todas sus subcuentas.</div>
            </div>
            <span class="badge bg-primary js-selected-count">0 seleccionadas</span>
        </div>
        <div class="input-group input-group-sm mt-2">
            <span class="input-group-text"><i class="ti ti-search"></i></span>
            <input type="search" class="form-control js-budget-search" placeholder="Filtrar por cuenta o subcuenta...">
        </div>
    </div>

    <div class="js-hidden-inputs"></div>

    <div class="p-2 js-category-list" style="max-height: 360px; overflow-y: auto;">
        @foreach ($groupedCedulas as $group)
            @php
                $category = $group['category'];
                $cedulas = $group['cedulas'];
                $categoryKey = $category?->id ?? 'none';
                $categorySelectedCount = $cedulas->filter(fn ($cedula) => $selectedIds->contains((int) $cedula->id))->count();
            @endphp
            <div class="border rounded mb-2 js-category-card"
                 data-search="{{ Str::lower(($category?->code ?? '') . ' ' . ($category?->name ?? '') . ' ' . $cedulas->pluck('name')->join(' ')) }}">
                <div class="d-flex gap-2 align-items-start p-2 bg-white border-bottom">
                    <input class="form-check-input mt-1 js-category-check"
                           type="checkbox"
                           id="{{ $selectorId }}-cat-{{ $categoryKey }}"
                           data-category="{{ $categoryKey }}">
                    <label class="form-check-label flex-grow-1" for="{{ $selectorId }}-cat-{{ $categoryKey }}">
                        <span class="fw-semibold">{{ $category?->code ?? 'S/C' }} - {{ $category?->name ?? 'Sin cuenta' }}</span>
                        <span class="badge bg-light text-dark border ms-1">{{ $cedulas->count() }} subcuentas</span>
                        <span class="badge bg-success ms-1 js-category-selected-count" data-category="{{ $categoryKey }}">{{ $categorySelectedCount }}</span>
                    </label>
                </div>
                <div class="row g-0">
                    @foreach ($cedulas as $cedula)
                        @php
                            $isSelected = $selectedIds->contains((int) $cedula->id);
                            $subaccount = $cedula->subaccount ?? null;
                        @endphp
                        <div class="col-md-6 js-cedula-row"
                             data-category="{{ $categoryKey }}"
                             data-id="{{ $cedula->id }}"
                             data-label="{{ Str::lower(($category?->code ?? '') . ' ' . ($category?->name ?? '') . ' ' . $cedula->name) }}">
                            <label class="d-flex gap-2 align-items-start p-2 mb-0 border-end border-bottom cursor-pointer">
                                <input class="form-check-input mt-1 js-cedula-check"
                                       type="checkbox"
                                       value="{{ $cedula->id }}"
                                       data-category="{{ $categoryKey }}"
                                       data-label="{{ $cedula->name }}"
                                       @checked($isSelected)>
                                <span>
                                    <span class="d-block fw-semibold">{{ $cedula->name }}</span>
                                    <small class="text-muted">
                                        {{ $category?->code ?? 'S/C' }}{{ $subaccount?->code ? ' / '.$subaccount->code : '' }}
                                    </small>
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

@once
    @push('styles')
        <style>
            .budget-cedula-selector .cursor-pointer {
                cursor: pointer;
            }

            .budget-cedula-selector .js-cedula-row:hover {
                background: #f8f9fa;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            function initializeBudgetCedulaSelector(rootSelector) {
                const $root = $(rootSelector);
                const $hiddenInputs = $root.find('.js-hidden-inputs');
                const $selectedCount = $root.find('.js-selected-count');

                function selectedValues() {
                    return $root.find('.js-cedula-check:checked').map(function() {
                        return $(this).val();
                    }).get();
                }

                function syncHiddenInputs() {
                    const values = selectedValues();
                    $hiddenInputs.empty();

                    values.forEach(function(value) {
                        $hiddenInputs.append(`<input type="hidden" name="budget_cedula_ids[]" value="${value}">`);
                    });

                    $selectedCount.text(`${values.length} seleccionada${values.length === 1 ? '' : 's'}`);
                }

                function syncCategoryState(category) {
                    const $checks = $root.find(`.js-cedula-check[data-category="${category}"]`);
                    const checked = $checks.filter(':checked').length;
                    const $categoryCheck = $root.find(`.js-category-check[data-category="${category}"]`);

                    $categoryCheck.prop('checked', checked > 0 && checked === $checks.length);
                    $categoryCheck.prop('indeterminate', checked > 0 && checked < $checks.length);
                    $root.find(`.js-category-selected-count[data-category="${category}"]`).text(checked);
                }

                function syncAll() {
                    const categories = new Set($root.find('.js-cedula-check').map(function() {
                        return $(this).data('category');
                    }).get());

                    categories.forEach(syncCategoryState);
                    syncHiddenInputs();
                }

                $root.on('change', '.js-category-check', function() {
                    const category = $(this).data('category');
                    $root.find(`.js-cedula-check[data-category="${category}"]`).prop('checked', $(this).prop('checked'));
                    syncAll();
                });

                $root.on('change', '.js-cedula-check', syncAll);

                $root.on('input', '.js-budget-search', function() {
                    const term = $(this).val().toLowerCase().trim();

                    $root.find('.js-category-card').each(function() {
                        const $card = $(this);
                        const cardMatches = !term || String($card.data('search')).includes(term);
                        let visibleRows = 0;

                        $card.find('.js-cedula-row').each(function() {
                            const rowMatches = !term || cardMatches || String($(this).data('label')).includes(term);
                            $(this).toggle(rowMatches);
                            if (rowMatches) {
                                visibleRows++;
                            }
                        });

                        $card.toggle(cardMatches || visibleRows > 0);
                    });
                });

                syncAll();
            }
        </script>
    @endpush
@endonce
