@php
    $movement = $budgetMovement ?? null;
    $origin = $movement?->originDetails()->first();
    $destination = $movement?->destinationDetails()->first();
    $adjustment = $movement?->adjustmentDetails()->first();
    $months = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
@endphp
<form method="POST" action="{{ $movement ? route('budget_movements.update', $movement) : route('budget_movements.store') }}" id="budgetMovementForm">
    @csrf @if($movement) @method('PUT') @endif
    <section class="bm-form-section"><span class="bm-kicker">1. Tipo de solicitud</span><h2>¿Qué necesitas hacer?</h2>
        <div class="row g-3"><div class="col-md-4"><label class="form-label">Movimiento</label><select name="movement_type" id="movement_type" class="form-select" required><option value="AMPLIACION" @selected(old('movement_type',$movement?->movement_type)==='AMPLIACION')>Ampliar presupuesto</option><option value="TRANSFERENCIA" @selected(old('movement_type',$movement?->movement_type)==='TRANSFERENCIA')>Transferir presupuesto</option><option value="REDUCCION" @selected(old('movement_type',$movement?->movement_type)==='REDUCCION')>Reducir presupuesto</option></select></div><div class="col-md-4"><label class="form-label">Año fiscal</label><input class="form-control" type="number" name="fiscal_year" value="{{ old('fiscal_year',$movement?->fiscal_year ?? $currentYear) }}" required></div><div class="col-md-4"><label class="form-label">Fecha</label><input class="form-control" type="date" name="movement_date" value="{{ old('movement_date',optional($movement?->movement_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required></div></div>
    </section>
    <section class="bm-form-section js-single"><span class="bm-kicker">2. Centro a afectar</span><h2>Tu centro de costo</h2>
        <div class="row g-3"><div class="col-md-6"><label class="form-label">Centro de costo</label><select name="cost_center_id" class="form-select js-cost-center-select" data-placeholder="Busca por clave o nombre del centro">@foreach($ownedCostCenters as $center)<option value="{{ $center->id }}" @selected(old('cost_center_id',$adjustment?->cost_center_id)==$center->id)>{{ $center->code }} · {{ $center->name }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Mes</label><select class="form-select" name="month"><option value="">Selecciona</option>@foreach($months as $number => $label)<option value="{{ $number }}" @selected(old('month',$adjustment?->month)==$number)>{{ $label }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Monto</label><div class="input-group"><span class="input-group-text">$</span><input class="form-control js-money-input" type="text" inputmode="decimal" name="total_amount" value="{{ old('total_amount',$movement?->total_amount) }}" required><span class="input-group-text">MXN</span></div></div></div>
        <div class="row g-3 mt-0"><div class="col-md-6"><label class="form-label">Cuenta</label><select name="expense_category_id" id="expense_category_id" class="form-select js-account-select" data-target="budget_cedula_id" data-placeholder="Busca una cuenta"><option value="">Selecciona</option>@foreach($expenseCategories as $category)<option value="{{ $category->id }}" @selected(old('expense_category_id',$adjustment?->expense_category_id)==$category->id)>{{ $category->name }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Subcuenta</label><select name="budget_cedula_id" id="budget_cedula_id" class="form-select js-budget-cedula-select" data-selected="{{ old('budget_cedula_id',$adjustment?->budget_cedula_id) }}" data-placeholder="Primero selecciona una cuenta" disabled><option value="">Primero selecciona una cuenta</option></select></div></div>
    </section>
    <section class="bm-form-section js-transfer d-none"><span class="bm-kicker">2. Transferencia</span><h2>Indica de dónde sale y a dónde llega</h2>
        @foreach(['origin' => ['Origen', $originCostCenters, $origin], 'destination' => ['Destino', $ownedCostCenters, $destination]] as $prefix => [$label,$centers,$detail])
        <div class="row g-3 {{ $prefix==='destination'?'mt-1':'' }}"><div class="col-12"><strong>{{ $label }}</strong>@if($prefix==='destination') <small class="text-muted">Solo puedes seleccionar centros de los que eres responsable.</small>@endif</div><div class="col-md-5"><label class="form-label">Centro de costo</label><select name="{{ $prefix }}_cost_center_id" class="form-select js-cost-center-select" data-placeholder="Busca por clave o nombre del centro"><option value="">Selecciona</option>@foreach($centers as $center)<option value="{{ $center->id }}" @selected(old($prefix.'_cost_center_id',$detail?->cost_center_id)==$center->id)>{{ $center->code }} · {{ $center->name }}</option>@endforeach</select></div><div class="col-md-2"><label class="form-label">Mes</label><select class="form-select" name="{{ $prefix }}_month"><option value="">Selecciona</option>@foreach($months as $number => $monthLabel)<option value="{{ $number }}" @selected(old($prefix.'_month',$detail?->month)==$number)>{{ $monthLabel }}</option>@endforeach</select></div><div class="col-md-5"><label class="form-label">Cuenta</label><select name="{{ $prefix }}_expense_category_id" id="{{ $prefix }}_expense_category_id" class="form-select js-account-select" data-target="{{ $prefix }}_budget_cedula_id" data-placeholder="Busca una cuenta"><option value="">Selecciona</option>@foreach($expenseCategories as $category)<option value="{{ $category->id }}" @selected(old($prefix.'_expense_category_id',$detail?->expense_category_id)==$category->id)>{{ $category->name }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Subcuenta</label><select name="{{ $prefix }}_budget_cedula_id" id="{{ $prefix }}_budget_cedula_id" class="form-select js-budget-cedula-select" data-selected="{{ old($prefix.'_budget_cedula_id',$detail?->budget_cedula_id) }}" data-placeholder="Primero selecciona una cuenta" disabled><option value="">Primero selecciona una cuenta</option></select></div>@if($prefix==='destination')<div class="col-md-6"><label class="form-label">Monto a transferir</label><div class="input-group"><span class="input-group-text">$</span><input class="form-control js-money-input" type="text" inputmode="decimal" name="total_amount" value="{{ old('total_amount',$movement?->total_amount) }}"><span class="input-group-text">MXN</span></div></div>@endif</div>
        @endforeach
    </section>
    <section class="bm-form-section"><span class="bm-kicker">3. Justificación</span><h2>Explica la necesidad del movimiento</h2><textarea name="justification" class="form-control" rows="4" minlength="10" required>{{ old('justification',$movement?->justification) }}</textarea><small class="text-muted">La justificación será visible para los responsables y Dirección General.</small></section>
    @if($errors->any())<div class="alert alert-danger"><strong>Revisa los datos capturados.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="d-flex justify-content-end gap-2"><a class="btn btn-light" href="{{ route('budget_movements.index') }}">Cancelar</a><button class="btn btn-primary bm-save" type="submit">{{ $movement ? 'Guardar y reenviar' : 'Enviar solicitud' }}</button></div>
</form>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const cedulas = @json($budgetCedulas->map(fn ($cedula) => ['id' => $cedula->id, 'expense_category_id' => $cedula->expense_category_id, 'name' => $cedula->name])->values());
    const type = document.querySelector('#movement_type');
    const single = document.querySelector('.js-single');
    const transfer = document.querySelector('.js-transfer');
    const select2 = ($select) => {
        if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
        $select.select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $(document.body), placeholder: $select.data('placeholder'), minimumResultsForSearch: 0 });
    };
    const refreshCedulas = (account) => {
        const $cedula = $('#'+account.data('target'));
        const selected = String($cedula.data('selected') || '');
        const options = cedulas.filter(item => String(item.expense_category_id) === String(account.val()));
        if ($cedula.hasClass('select2-hidden-accessible')) $cedula.select2('destroy');
        $cedula.empty().prop('disabled', !options.length).append(new Option(options.length ? 'Selecciona una subcuenta' : 'Primero selecciona una cuenta', ''));
        options.forEach(item => $cedula.append(new Option(item.name, item.id, false, String(item.id) === selected)));
        select2($cedula);
        $cedula.data('selected', '');
    };
    const toggle = () => { const isTransfer = type.value === 'TRANSFERENCIA'; single.classList.toggle('d-none', isTransfer); transfer.classList.toggle('d-none', !isTransfer); };
    type.addEventListener('change', toggle); toggle();
    $('.js-cost-center-select, .js-account-select').each(function () { select2($(this)); });
    $('.js-account-select').each(function () { refreshCedulas($(this)); }).on('change', function () { const $cedula = $('#'+$(this).data('target')); $cedula.data('selected', ''); refreshCedulas($(this)); });
    const rawMoney = value => String(value || '').replace(/[^0-9.]/g, '');
    const formatMoney = input => { const value = rawMoney(input.value); input.value = value === '' ? '' : Number(value).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    document.querySelectorAll('.js-money-input').forEach(input => { formatMoney(input); input.addEventListener('focus', () => { input.value = rawMoney(input.value); }); input.addEventListener('blur', () => formatMoney(input)); });
    document.querySelector('#budgetMovementForm').addEventListener('submit', event => { document.querySelectorAll('.js-money-input').forEach(input => { input.value = rawMoney(input.value); }); const button = event.submitter; button.disabled = true; button.textContent = 'Enviando…'; });
});
</script>
@endpush
