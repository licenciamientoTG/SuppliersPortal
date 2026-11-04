@php
    $movement = $budgetMovement ?? null;
    $origin = $movement?->originDetails()->first();
    $destination = $movement?->destinationDetails()->first();
    $adjustment = $movement?->adjustmentDetails()->first();
    $months = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    $selectedType = old('movement_type', $movement?->movement_type);
    $selectedYear = (int) old('fiscal_year', $movement?->fiscal_year ?? $currentYear);
    $fiscalYears = collect([$currentYear, $currentYear + 1, $selectedYear])->unique()->sort()->values();
    $attachments = $movement?->attachments ?? collect();
    $types = [
        'AMPLIACION' => ['Ampliar', 'Agrega presupuesto a una subcuenta de tu centro.', 'ti-trending-up'],
        'TRANSFERENCIA' => ['Transferir', 'Mueve presupuesto de una subcuenta a otra.', 'ti-arrows-exchange'],
        'REDUCCION' => ['Reducir', 'Libera presupuesto que ya no vas a usar.', 'ti-trending-down'],
    ];
@endphp
<form method="POST" enctype="multipart/form-data" novalidate action="{{ $movement ? route('budget_movements.update', $movement) : route('budget_movements.store') }}" id="budgetMovementForm">
    @csrf @if($movement) @method('PUT') @endif

    <div id="bmFormErrors" class="alert alert-danger {{ $errors->any() ? '' : 'd-none' }}" role="alert" tabindex="-1">
        <strong>No se pudo enviar la solicitud. Corrige lo siguiente:</strong>
        <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        <small class="d-block mt-2">Tus datos siguen capturados; no necesitas volver a llenar el formulario.</small>
    </div>

    {{-- 1. Tipo y periodo --}}
    <section class="bm-form-section">
        <h2 id="bmTypeLabel">1. ¿Qué necesitas hacer?</h2>
        <div class="bm-type-options" role="radiogroup" aria-labelledby="bmTypeLabel">
            @foreach($types as $value => [$title, $hint, $icon])
                <label class="bm-type-option">
                    <input type="radio" name="movement_type" value="{{ $value }}" @checked($selectedType === $value)>
                    <span class="bm-type-card"><i class="ti {{ $icon }}" aria-hidden="true"></i><strong>{{ $title }}</strong><small>{{ $hint }}</small></span>
                </label>
            @endforeach
        </div>
        <div class="row g-3 mt-1">
            <div class="col-sm-6 col-md-3">
                <label class="form-label" for="fiscal_year">Año fiscal</label>
                <select class="form-select" id="fiscal_year" name="fiscal_year">
                    @foreach($fiscalYears as $year)<option value="{{ $year }}" @selected($selectedYear === $year)>{{ $year }}</option>@endforeach
                </select>
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label" for="movement_date">Fecha de la solicitud</label>
                <input class="form-control" type="date" id="movement_date" name="movement_date" value="{{ old('movement_date', optional($movement?->movement_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
            </div>
            <div class="col-md-6 d-flex align-items-end"><p class="bm-section-help mb-1">La fecha solo registra cuándo se solicita. El mes que se afecta se elige en cada partida.</p></div>
        </div>
    </section>

    {{-- 2. Partidas --}}
    <section class="bm-form-section js-lines-pending {{ $selectedType ? 'd-none' : '' }}">
        <h2>2. Partidas</h2>
        <p class="bm-section-help mb-0">Elige primero qué necesitas hacer para capturar las partidas.</p>
    </section>

    <section class="bm-form-section js-single d-none">
        <h2>2. Partida a afectar</h2>
        <p class="bm-section-help">Solo se muestran los centros de costo de los que eres responsable.</p>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="cost_center_id">Centro de costo</label>
                <select name="cost_center_id" id="cost_center_id" class="form-select js-cost-center-select" data-placeholder="Busca por clave o nombre del centro">
                    @foreach($ownedCostCenters as $center)<option value="{{ $center->id }}" @selected(old('cost_center_id', $adjustment?->cost_center_id) == $center->id)>{{ $center->code }} · {{ $center->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="month">Mes</label>
                <select class="form-select" id="month" name="month">
                    <option value="">Selecciona</option>
                    @foreach($months as $number => $label)<option value="{{ $number }}" @selected(old('month', $adjustment?->month ?? now()->month) == $number)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="expense_category_id">Cuenta</label>
                <select name="expense_category_id" id="expense_category_id" class="form-select js-account-select" data-target="budget_cedula_id" data-placeholder="Busca una cuenta">
                    <option value="">Selecciona</option>
                    @foreach($expenseCategories as $category)<option value="{{ $category->id }}" @selected(old('expense_category_id', $adjustment?->expense_category_id) == $category->id)>{{ $category->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="budget_cedula_id">Subcuenta</label>
                <select name="budget_cedula_id" id="budget_cedula_id" class="form-select js-budget-cedula-select" data-selected="{{ old('budget_cedula_id', $adjustment?->budget_cedula_id) }}" data-placeholder="Primero selecciona una cuenta" disabled><option value="">Primero selecciona una cuenta</option></select>
            </div>
        </div>
        @include('budget_movements.workflow._budget_preview', ['context' => 'single', 'title' => 'Saldo de esta subcuenta', 'movementLabel' => 'Movimiento'])
    </section>

    <section class="bm-form-section js-transfer d-none">
        <h2>2. ¿De dónde sale y a dónde llega?</h2>
        @foreach(['origin' => ['Origen', $originCostCenters, $origin], 'destination' => ['Destino', $ownedCostCenters, $destination]] as $prefix => [$label, $centers, $detail])
            <div class="bm-line {{ $prefix === 'destination' ? 'mt-4' : '' }}">
                <h3 class="bm-line-title">{{ $label }}</h3>
                <p class="bm-section-help">{{ $prefix === 'destination' ? 'Solo puedes seleccionar centros de los que eres responsable.' : 'Se muestran los centros de costo activos de tu empresa.' }}</p>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="{{ $prefix }}_cost_center_id">Centro de costo</label>
                        <select name="{{ $prefix }}_cost_center_id" id="{{ $prefix }}_cost_center_id" class="form-select js-cost-center-select" data-placeholder="Busca por clave o nombre del centro">
                            <option value="">Selecciona</option>
                            @foreach($centers as $center)<option value="{{ $center->id }}" data-responsible-id="{{ $center->responsible_user_id }}" data-responsible="{{ $center->responsible?->name }}" @selected(old($prefix.'_cost_center_id', $detail?->cost_center_id) == $center->id)>{{ $center->code }} · {{ $center->name }}</option>@endforeach
                        </select>
                        @if($prefix === 'origin')<small class="text-muted d-block mt-1 js-origin-responsible" aria-live="polite"></small>@endif
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="{{ $prefix }}_month">Mes</label>
                        <select class="form-select" id="{{ $prefix }}_month" name="{{ $prefix }}_month">
                            <option value="">Selecciona</option>
                            @foreach($months as $number => $monthLabel)<option value="{{ $number }}" @selected(old($prefix.'_month', $detail?->month ?? now()->month) == $number)>{{ $monthLabel }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="{{ $prefix }}_expense_category_id">Cuenta</label>
                        <select name="{{ $prefix }}_expense_category_id" id="{{ $prefix }}_expense_category_id" class="form-select js-account-select" data-target="{{ $prefix }}_budget_cedula_id" data-placeholder="Busca una cuenta">
                            <option value="">Selecciona</option>
                            @foreach($expenseCategories as $category)<option value="{{ $category->id }}" @selected(old($prefix.'_expense_category_id', $detail?->expense_category_id) == $category->id)>{{ $category->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="{{ $prefix }}_budget_cedula_id">Subcuenta</label>
                        <select name="{{ $prefix }}_budget_cedula_id" id="{{ $prefix }}_budget_cedula_id" class="form-select js-budget-cedula-select" data-selected="{{ old($prefix.'_budget_cedula_id', $detail?->budget_cedula_id) }}" data-placeholder="Primero selecciona una cuenta" disabled><option value="">Primero selecciona una cuenta</option></select>
                    </div>
                </div>
                @include('budget_movements.workflow._budget_preview', ['context' => $prefix, 'title' => 'Saldo de la subcuenta '.strtolower($label), 'movementLabel' => $prefix === 'origin' ? 'Sale' : 'Llega'])
            </div>
        @endforeach
    </section>

    {{-- 3. Monto --}}
    <section class="bm-form-section">
        <h2>3. Monto</h2>
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label" for="total_amount">Monto del movimiento</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input class="form-control js-money-input" type="text" inputmode="decimal" id="total_amount" name="total_amount" aria-describedby="totalAmountHelp" value="{{ old('total_amount', $movement?->total_amount) }}">
                    <span class="input-group-text">MXN</span>
                </div>
            </div>
            <div class="col-md-7 d-flex align-items-end"><p class="bm-section-help mb-1" id="totalAmountHelp">Elige qué necesitas hacer para ver cómo se aplica el monto.</p></div>
        </div>
    </section>

    {{-- 4. Justificación y soporte --}}
    <section class="bm-form-section">
        <h2>4. Justificación y soporte</h2>
        <div class="d-flex justify-content-between align-items-baseline gap-2">
            <label class="form-label" for="justification">¿Por qué se necesita este movimiento?</label>
            <small class="text-muted js-justification-count" aria-live="polite">0 / 1000</small>
        </div>
        <textarea name="justification" id="justification" class="form-control" rows="4" maxlength="1000" aria-describedby="justificationHelp">{{ old('justification', $movement?->justification) }}</textarea>
        <small class="text-muted d-block mt-1" id="justificationHelp">Mínimo 10 caracteres. La verán los responsables de los centros y Dirección General.</small>

        <label class="form-label mt-3" for="supporting_document">Documento justificativo{{ $movement ? ' (opcional)' : '' }}</label>
        <input class="form-control" type="file" id="supporting_document" name="supporting_document" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.docx" aria-describedby="supportingDocumentHelp">
        <small class="text-muted d-block mt-1" id="supportingDocumentHelp">PDF, JPG, PNG, XLSX o DOCX de máximo 10 MB.{{ $movement ? ' Si adjuntas uno nuevo se agrega a los existentes; los documentos guardados no se reemplazan ni eliminan.' : '' }}</small>
        @if($attachments->isNotEmpty())
            <div class="bm-attachments mt-2">
                <span>Ya adjuntos:</span>
                <ul class="mb-0">@foreach($attachments as $attachment)<li><i class="ti ti-paperclip" aria-hidden="true"></i> {{ $attachment->original_name }}</li>@endforeach</ul>
            </div>
        @endif
    </section>

    {{-- Resumen --}}
    <section class="bm-summary" aria-live="polite">
        <h2>Antes de enviar</h2>
        <p class="mb-0 js-summary">Elige qué necesitas hacer para ver el resumen.</p>
    </section>

    <div class="d-flex justify-content-end gap-2 mt-3">
        <a class="btn btn-light" href="{{ route('budget_movements.index') }}">Cancelar</a>
        <button class="btn btn-primary bm-save" type="submit">{{ $movement ? 'Guardar y reenviar' : 'Enviar solicitud' }}</button>
    </div>
</form>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const cedulas = @json($budgetCedulas->map(fn ($cedula) => ['id' => $cedula->id, 'expense_category_id' => $cedula->expense_category_id, 'name' => $cedula->name])->values());
    const currentUserId = @json(auth()->id());
    const isEdit = @json((bool) $movement);
    const form = document.querySelector('#budgetMovementForm');
    const single = form.querySelector('.js-single');
    const transfer = form.querySelector('.js-transfer');
    const linesPending = form.querySelector('.js-lines-pending');
    const amountField = form.querySelector('#total_amount');
    const amountHelp = form.querySelector('#totalAmountHelp');
    const summary = form.querySelector('.js-summary');
    const currentType = () => form.elements.movement_type.value || '';

    // ---- Formato de dinero ----
    const rawMoney = value => {
        const [whole = '', ...decimals] = String(value || '').replace(/,/g, '').replace(/[^0-9.]/g, '').split('.');
        return decimals.length ? `${whole}.${decimals.join('').slice(0, 2)}` : whole;
    };
    const liveMoney = value => {
        const raw = rawMoney(value);
        if (raw === '') return '';
        const [whole, decimal] = raw.split('.');
        const grouped = (whole || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return decimal === undefined ? grouped : `${grouped}.${decimal}`;
    };
    const currency = value => Number(value || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 2 });

    // ---- Select2 con badge de disponible por subcuenta ----
    const balanceTemplate = option => {
        if (!option.id || !option.element) return option.text;
        const $row = $('<span class="bm-cedula-option"></span>').append($('<span class="bm-cedula-name"></span>').text(option.text));
        const available = option.element.dataset.available;
        if (available === undefined) return $row;
        const amount = Number(available);
        const [css, label] = available === '' ? ['text-bg-light border', 'Sin presupuesto'] : [amount > 0 ? 'text-bg-success' : 'text-bg-secondary', currency(amount)];
        return $row.append($('<span class="badge bm-cedula-badge"></span>').addClass(css).text(label));
    };
    const select2 = $select => {
        if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
        const templates = $select.hasClass('js-budget-cedula-select') ? { templateResult: balanceTemplate, templateSelection: balanceTemplate } : {};
        $select.select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $(document.body), placeholder: $select.data('placeholder'), minimumResultsForSearch: 0, ...templates });
    };
    const lineValues = prefix => ({
        cost_center_id: form.elements[`${prefix}cost_center_id`]?.value,
        month: form.elements[`${prefix}month`]?.value,
        expense_category_id: form.elements[`${prefix}expense_category_id`]?.value,
        budget_cedula_id: form.elements[`${prefix}budget_cedula_id`]?.value,
        fiscal_year: form.elements.fiscal_year.value,
    });
    const loadCedulaBalances = $cedula => {
        const select = $cedula[0];
        const prefix = select.id.replace('budget_cedula_id', '');
        const options = [...select.options].filter(option => option.value);
        options.forEach(option => delete option.dataset.available);
        $cedula.trigger('change.select2');
        const { budget_cedula_id: _ignored, ...values } = { ...lineValues(prefix), context: prefix ? prefix.slice(0, -1) : 'single' };
        if (!options.length || !values.cost_center_id || !values.month || !values.expense_category_id || !values.fiscal_year) return;
        const requestKey = `${Date.now()}-${Math.random()}`; select.dataset.balanceKey = requestKey;
        const url = new URL(@json(route('budget_movements.cedula-balances')), window.location.origin);
        Object.entries(values).forEach(([key, value]) => url.searchParams.set(key, value));
        fetch(url, { headers: { Accept: 'application/json' } }).then(response => response.ok ? response.json() : Promise.reject()).then(data => {
            if (select.dataset.balanceKey !== requestKey) return;
            options.forEach(option => { const balance = data.balances?.[option.value]; option.dataset.available = balance === undefined ? '' : balance; });
            $cedula.trigger('change.select2');
        }).catch(() => {});
    };
    const refreshCedulas = $account => {
        const $cedula = $('#' + $account.data('target'));
        const selected = String($cedula.data('selected') || '');
        const options = cedulas.filter(item => String(item.expense_category_id) === String($account.val()));
        if ($cedula.hasClass('select2-hidden-accessible')) $cedula.select2('destroy');
        $cedula.empty().prop('disabled', !options.length).append(new Option(options.length ? 'Selecciona una subcuenta' : 'Primero selecciona una cuenta', ''));
        options.forEach(item => $cedula.append(new Option(item.name, item.id, false, String(item.id) === selected)));
        select2($cedula);
        $cedula.data('selected', '');
        $cedula.trigger('change');
        loadCedulaBalances($cedula);
    };

    // ---- Vista previa de saldo ----
    const previewContexts = { single: '', origin: 'origin_', destination: 'destination_' };
    const effectFor = context => context === 'origin' || (context === 'single' && currentType() === 'REDUCCION') ? 'DECREASE' : 'INCREASE';
    const renderEmptyPreview = (preview, message) => {
        preview.classList.remove('is-loading', 'is-ready', 'is-warning');
        delete preview.dataset.available; delete preview.dataset.insufficient;
        preview.querySelector('.bm-preview-state').textContent = message;
        preview.querySelectorAll('[data-budget-value]').forEach(value => value.textContent = '—');
    };
    const updatePreview = context => {
        const preview = form.querySelector(`[data-budget-preview="${context}"]`); if (!preview) return;
        const effect = effectFor(context);
        const values = { ...lineValues(previewContexts[context]), amount: rawMoney(amountField.value), effect, context };
        if (!values.cost_center_id || !values.month || !values.expense_category_id || !values.budget_cedula_id || !values.fiscal_year) return renderEmptyPreview(preview, 'Completa centro, mes, cuenta y subcuenta.');
        preview.classList.add('is-loading'); preview.querySelector('.bm-preview-state').textContent = 'Consultando saldo actual…';
        const requestKey = `${Date.now()}-${Math.random()}`; preview.dataset.requestKey = requestKey;
        const url = new URL(@json(route('budget_movements.budget-snapshot')), window.location.origin);
        Object.entries(values).forEach(([key, value]) => url.searchParams.set(key, value || '0'));
        fetch(url, { headers: { Accept: 'application/json' } }).then(response => response.ok ? response.json() : Promise.reject()).then(data => {
            if (preview.dataset.requestKey !== requestKey) return;
            const insufficient = !data.has_sufficient_available;
            preview.classList.remove('is-loading'); preview.classList.add('is-ready'); preview.classList.toggle('is-warning', insufficient);
            preview.dataset.available = data.available_amount; preview.dataset.insufficient = insufficient ? '1' : '';
            preview.querySelector('[data-budget-value="assigned"]').textContent = currency(data.assigned_amount);
            preview.querySelector('[data-budget-value="available"]').textContent = currency(data.available_amount);
            preview.querySelector('[data-budget-value="movement"]').textContent = `${effect === 'DECREASE' ? '−' : '+'}${currency(data.movement_amount)}`;
            preview.querySelector('[data-budget-value="projected"]').textContent = currency(data.projected_available_amount);
            preview.querySelector('.bm-preview-state').textContent = insufficient ? 'El monto supera el disponible actual; no podrás enviar la solicitud así.' : (data.message || 'Saldo disponible para esta subcuenta.');
        }).catch(() => { if (preview.dataset.requestKey === requestKey) renderEmptyPreview(preview, 'No fue posible consultar el saldo. Intenta de nuevo.'); });
    };
    const refreshVisiblePreviews = () => {
        if (currentType() === 'TRANSFERENCIA') { updatePreview('origin'); updatePreview('destination'); }
        else if (currentType()) updatePreview('single');
    };

    // ---- Responsable del origen y resumen ----
    const selectedOption = name => { const option = form.elements[name]?.selectedOptions?.[0]; return option && option.value ? option : null; };
    const originResponsible = () => {
        const option = selectedOption('origin_cost_center_id');
        if (!option) return null;
        return String(option.dataset.responsibleId) === String(currentUserId) ? 'tú' : (option.dataset.responsible || 'el responsable del centro origen');
    };
    const lineText = prefix => {
        const center = selectedOption(`${prefix}cost_center_id`), cedula = selectedOption(`${prefix}budget_cedula_id`), month = selectedOption(`${prefix}month`);
        return center && cedula && month ? `${center.text.trim()} · ${cedula.text.trim()} (${month.text.trim()} ${form.elements.fiscal_year.value})` : null;
    };
    const renderSummary = () => {
        const type = currentType();
        const amount = Number(rawMoney(amountField.value));
        const parts = [];
        const add = (text, strong = false) => parts.push([text, strong]);
        const responsible = form.querySelector('.js-origin-responsible');
        if (responsible) { const name = originResponsible(); responsible.textContent = name ? `Validará esta transferencia: ${name === 'tú' ? 'tú, como responsable del centro' : name}.` : ''; }
        if (!type) add('Elige qué necesitas hacer para ver el resumen.');
        else if (type === 'TRANSFERENCIA') {
            const from = lineText('origin_'), to = lineText('destination_');
            if (!from || !to || !(amount > 0)) add('Completa el origen, el destino y el monto para ver el resumen.');
            else {
                const name = originResponsible();
                add('Transferirás '); add(currency(amount), true); add(' de '); add(from, true); add(' a '); add(to, true);
                add(`. Primero la valida ${name === 'tú' ? 'tú mismo, como responsable del centro origen' : name}; después pasa a Dirección General para su autorización.`);
            }
        } else {
            const line = lineText('');
            if (!line || !(amount > 0)) add('Completa la partida y el monto para ver el resumen.');
            else { add(type === 'REDUCCION' ? 'Reducirás ' : 'Ampliarás '); add(currency(amount), true); add(type === 'REDUCCION' ? ' de ' : ' en '); add(line, true); add('. La solicitud pasa a Dirección General para su autorización.'); }
        }
        summary.replaceChildren(...parts.map(([text, strong]) => { if (!strong) return document.createTextNode(text); const node = document.createElement('strong'); node.textContent = text; return node; }));
    };
    const amountHelpText = { AMPLIACION: 'Se suma al presupuesto asignado de la subcuenta.', TRANSFERENCIA: 'Sale del origen y llega al destino por el mismo importe.', REDUCCION: 'Se resta del presupuesto de la subcuenta; no puede ser mayor a su disponible.' };

    const toggle = () => {
        const type = currentType();
        linesPending.classList.toggle('d-none', !!type);
        single.classList.toggle('d-none', !type || type === 'TRANSFERENCIA');
        transfer.classList.toggle('d-none', type !== 'TRANSFERENCIA');
        amountHelp.textContent = amountHelpText[type] || 'Elige qué necesitas hacer para ver cómo se aplica el monto.';
        refreshVisiblePreviews();
        renderSummary();
    };

    // ---- Errores ----
    const errorBox = form.querySelector('#bmFormErrors');
    const submitButton = form.querySelector('.bm-save');
    const submitLabel = submitButton.textContent;
    const anchorFor = name => {
        if (name === 'movement_type') return form.querySelector('.bm-type-options');
        const field = form.elements[name];
        if (!field || field instanceof RadioNodeList || field.type === 'hidden') return null;
        const group = field.closest('.input-group');
        if (group) return group;
        const select2Box = field.nextElementSibling;
        return select2Box?.classList.contains('select2') ? select2Box : field;
    };
    const clearFieldError = name => {
        const anchor = anchorFor(name); if (!anchor) return;
        form.elements[name]?.classList?.remove('is-invalid');
        anchor.classList.remove('bm-invalid');
        const feedback = anchor.nextElementSibling;
        if (feedback?.classList.contains('js-field-error')) feedback.remove();
    };
    const clearErrors = () => {
        errorBox.classList.add('d-none');
        errorBox.querySelector('ul').innerHTML = '';
        form.querySelectorAll('.js-field-error').forEach(item => item.remove());
        form.querySelectorAll('.is-invalid').forEach(item => item.classList.remove('is-invalid'));
        form.querySelectorAll('.bm-invalid').forEach(item => item.classList.remove('bm-invalid'));
    };
    const showErrors = errors => {
        const list = errorBox.querySelector('ul');
        const messages = new Set();
        Object.entries(errors).forEach(([name, value]) => {
            const message = Array.isArray(value) ? value[0] : value;
            messages.add(message);
            const anchor = anchorFor(name); if (!anchor) return;
            clearFieldError(name);
            form.elements[name]?.classList?.add('is-invalid');
            anchor.classList.add('bm-invalid');
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-block js-field-error';
            feedback.textContent = message;
            anchor.insertAdjacentElement('afterend', feedback);
        });
        messages.forEach(message => { const item = document.createElement('li'); item.textContent = message; list.append(item); });
        errorBox.classList.remove('d-none');
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        errorBox.focus({ preventScroll: true });
    };
    const validateForm = () => {
        const errors = {};
        const value = name => String(form.elements[name]?.value ?? '').trim();
        const require = (name, message) => { if (!value(name)) errors[name] = message; };
        const type = currentType();
        if (!type) errors.movement_type = 'Elige qué necesitas hacer: ampliar, transferir o reducir.';
        require('fiscal_year', 'Selecciona el año fiscal.');
        require('movement_date', 'Indica la fecha de la solicitud.');
        if (type === 'TRANSFERENCIA') {
            [['origin', 'origen'], ['destination', 'destino']].forEach(([prefix, label]) => {
                require(`${prefix}_cost_center_id`, `Selecciona el centro de costo ${label}.`);
                require(`${prefix}_month`, `Selecciona el mes ${label}.`);
                require(`${prefix}_expense_category_id`, `Selecciona la cuenta ${label}.`);
                require(`${prefix}_budget_cedula_id`, `Selecciona la subcuenta ${label}.`);
            });
            const sameLine = ['cost_center_id', 'month', 'expense_category_id', 'budget_cedula_id'].every(field => value(`origin_${field}`) && value(`origin_${field}`) === value(`destination_${field}`));
            if (sameLine) errors.destination_budget_cedula_id = 'El origen y el destino son la misma partida. Cambia al menos el centro de costo, el mes, la cuenta o la subcuenta.';
        } else if (type) {
            require('cost_center_id', 'Selecciona el centro de costo.');
            require('month', 'Selecciona el mes.');
            require('expense_category_id', 'Selecciona la cuenta.');
            require('budget_cedula_id', 'Selecciona la subcuenta.');
        }
        const amount = Number(rawMoney(amountField.value));
        if (!(amount > 0)) errors.total_amount = 'Captura un monto mayor a 0.';
        else if (amount > 999999999.99) errors.total_amount = 'El monto es demasiado grande.';
        else if (type === 'TRANSFERENCIA' || type === 'REDUCCION') {
            // Bloqueo: no se puede sacar más de lo disponible en la subcuenta que disminuye.
            const preview = form.querySelector(`[data-budget-preview="${type === 'TRANSFERENCIA' ? 'origin' : 'single'}"]`);
            const label = type === 'TRANSFERENCIA' ? 'de la subcuenta origen' : 'de la subcuenta';
            if (preview?.classList.contains('is-loading')) errors.total_amount = 'Espera a que termine la consulta del saldo e intenta de nuevo.';
            else if (preview?.dataset.insufficient === '1') errors.total_amount = `El monto supera el disponible ${label} (${currency(preview.dataset.available)}). Ajusta el monto o elige otra subcuenta.`;
        }
        const file = form.elements.supporting_document.files?.[0];
        if (!file && !isEdit) errors.supporting_document = 'Adjunta el documento justificativo.';
        if (file) {
            const extension = file.name.split('.').pop().toLowerCase();
            if (!['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'docx'].includes(extension)) errors.supporting_document = 'El documento justificativo debe ser PDF, JPG, PNG, XLSX o DOCX.';
            else if (file.size > 10 * 1024 * 1024) errors.supporting_document = 'El documento justificativo no debe pesar más de 10 MB.';
        }
        const justification = value('justification');
        if (justification.length < 10) errors.justification = 'La justificación debe tener al menos 10 caracteres.';
        else if (justification.length > 1000) errors.justification = 'La justificación no debe exceder 1000 caracteres.';
        return errors;
    };

    // ---- Contador de justificación ----
    const justification = form.querySelector('#justification');
    const counter = form.querySelector('.js-justification-count');
    const updateCounter = () => { const length = justification.value.trim().length; counter.textContent = `${justification.value.length} / 1000`; counter.classList.toggle('text-danger', length > 0 && length < 10); };

    // ---- Inicialización ----
    $('.js-cost-center-select, .js-account-select').each(function () { select2($(this)); });
    $('.js-account-select').each(function () { refreshCedulas($(this)); }).on('change', function () { $('#' + $(this).data('target')).data('selected', ''); refreshCedulas($(this)); });
    $(form).on('change', 'select[name$="cost_center_id"], select[name$="month"], select[name="fiscal_year"]', function () {
        if (this.name === 'fiscal_year') $('.js-budget-cedula-select').each(function () { loadCedulaBalances($(this)); });
        else loadCedulaBalances($(`#${this.name.replace(/(cost_center_id|month)$/, '')}budget_cedula_id`));
    });
    $(form).on('change', 'select', () => { refreshVisiblePreviews(); renderSummary(); });
    form.querySelectorAll('input[name="movement_type"]').forEach(radio => radio.addEventListener('change', toggle));
    justification.addEventListener('input', updateCounter);

    const formatWhileTyping = () => {
        const previous = amountField.value;
        const cursor = amountField.selectionStart ?? previous.length;
        const significantBeforeCursor = rawMoney(previous.slice(0, cursor)).length;
        const formatted = liveMoney(previous);
        amountField.value = formatted;
        let newCursor = 0, significant = 0;
        while (newCursor < formatted.length && significant < significantBeforeCursor) { if (/\d|\./.test(formatted[newCursor])) significant++; newCursor++; }
        amountField.setSelectionRange(newCursor, newCursor);
    };
    const formatOnBlur = () => { const value = rawMoney(amountField.value); amountField.value = value === '' ? '' : Number(value).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    formatOnBlur();
    amountField.addEventListener('input', () => { formatWhileTyping(); refreshVisiblePreviews(); renderSummary(); });
    amountField.addEventListener('blur', () => { formatOnBlur(); refreshVisiblePreviews(); });

    updateCounter();
    toggle();

    // ---- Aviso al salir con cambios sin enviar ----
    let dirty = false, submitting = false;
    $(form).on('input change', event => { if (event.originalEvent) dirty = true; clearFieldError(event.target.name); });
    $(form).on('select2:select select2:clear', 'select', function () { dirty = true; clearFieldError(this.name); });
    window.addEventListener('beforeunload', event => { if (dirty && !submitting) { event.preventDefault(); event.returnValue = ''; } });

    // ---- Envío ----
    const resetButton = () => { submitting = false; submitButton.disabled = false; submitButton.textContent = submitLabel; };
    form.addEventListener('submit', async event => {
        event.preventDefault();
        clearErrors();
        const errors = validateForm();
        if (Object.keys(errors).length) return showErrors(errors);
        submitting = true;
        submitButton.disabled = true;
        submitButton.textContent = 'Enviando…';
        const body = new FormData(form);
        body.set('total_amount', rawMoney(amountField.value));
        let response;
        try {
            response = await fetch(form.action, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        } catch (error) {
            resetButton();
            return showErrors({ _network: 'No hubo conexión con el servidor. Revisa tu conexión a internet e intenta de nuevo.' });
        }
        const payload = await response.json().catch(() => ({}));
        if (response.ok && payload.redirect) { window.location.href = payload.redirect; return; }
        resetButton();
        if (response.status === 422 && payload.errors) return showErrors(payload.errors);
        const fallback = {
            403: 'No tienes permiso para registrar este movimiento. Verifica que seas responsable del centro de costo seleccionado.',
            413: 'El documento adjunto es demasiado grande para el servidor. Usa un archivo más ligero (máximo 10 MB).',
            419: 'Tu sesión expiró. Copia tu justificación, recarga la página e inicia sesión de nuevo.',
            422: 'El servidor rechazó la solicitud.',
        }[response.status];
        showErrors({ _server: payload.message && response.status !== 500 ? payload.message : (fallback || `Ocurrió un error inesperado en el servidor (código ${response.status}). Intenta de nuevo; si persiste, avisa a sistemas.`) });
    });
});
</script>
@endpush
