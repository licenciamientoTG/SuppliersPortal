@extends('layouts.zircos')

@section('title', 'Configuración de grupo de impuestos')
@section('page.title', 'Configuración de grupo de impuestos')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('tax-groups.index') }}">Grupos de impuestos</a></li>
    <li class="breadcrumb-item active">{{ $taxGroup->name }}</li>
@endsection

@push('styles')
<style>
    .tax-group-header { border: 1px solid #e2e9f0; border-radius: .8rem; background: #f7fbff; }
    .tax-group-metric { border-left: 3px solid #188ae2; padding-left: 1rem; }
    .tax-group-items thead th { background: #f7fbff; color: #355070; white-space: nowrap; }
    .tax-group-account { min-width: 320px; }
    @media (max-width: 767.98px) { .tax-group-account { min-width: 250px; } }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('tax-groups.index') }}" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i> Volver</a>
    <div class="d-flex gap-2"><a href="{{ route('tax-groups.edit', $taxGroup) }}" class="btn btn-outline-primary">Editar grupo</a><span class="badge {{ $taxGroup->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $taxGroup->is_active ? 'Activo' : 'Inactivo' }}</span></div>
</div>

<div class="tax-group-header p-4 mb-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <div class="text-muted small text-uppercase fw-semibold">Grupo compuesto · One Goal #{{ $taxGroup->one_goal_id }}</div>
            <h4 class="mb-1">{{ $taxGroup->name }}</h4>
            <p class="text-muted mb-0">La cuenta se asigna por componente; los códigos simples y tasas permanecen separados.</p>
        </div>
        <div class="row g-3 text-lg-end">
            <div class="col-6"><div class="tax-group-metric"><small class="text-muted d-block">Componentes</small><strong>{{ $taxGroup->items->count() }}</strong></div></div>
            <div class="col-6"><div class="tax-group-metric"><small class="text-muted d-block">Objeto SAT</small><strong>{{ $taxGroup->sat_tax_object ?: '—' }}</strong></div></div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('tax-groups.update', $taxGroup) }}">
    @csrf
    @method('PUT')
    <div class="card border-0 shadow-sm">
        <div class="card-body border-bottom py-3 d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center">
            <div>
                <h5 class="mb-1">Componentes y cuentas contables</h5>
                <p class="text-muted mb-0">Puedes corregir una cuenta sin alterar el catálogo simple ni la composición del grupo.</p>
            </div>
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Guardar cuentas</button>
        </div>
        <div class="table-responsive">
            <table class="table tax-group-items align-middle mb-0">
                <thead><tr><th>Impuesto simple</th><th class="text-end">Tasa / cuota</th><th>Cuenta contable</th><th class="text-center">CFDI</th><th class="text-end">Acción</th></tr></thead>
                <tbody>
                    @forelse ($taxGroup->items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->taxCode?->name ?? 'No localizado' }}</strong>
                            </td>
                            <td class="text-end">{{ $item->taxCode?->calculation_type === 'fixed_quota' ? '$' : '' }}{{ $item->taxCode ? number_format((float) $item->taxCode->rate, 4).($item->taxCode->calculation_type === 'percentage' ? '%' : '') : '—' }}</td>
                            <td class="tax-group-account">
                                <label class="visually-hidden" for="account-{{ $item->id }}">Cuenta para {{ $item->taxCode?->name }}</label>
                                <select id="account-{{ $item->id }}" name="items[{{ $item->id }}][ledger_account_id]" class="form-select account-select @error('items.'.$item->id.'.ledger_account_id') is-invalid @enderror">
                                    <option value="">Sin cuenta</option>
                                    @foreach ($ledgerAccounts as $account)
                                        <option value="{{ $account->id }}" @selected($item->ledger_account_id === $account->id)>{{ $account->display_label }}{{ ! $account->is_active ? ' (inactiva)' : '' }}</option>
                                    @endforeach
                                </select>
                                @error('items.'.$item->id.'.ledger_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </td>
                            <td class="text-center">
                                @if ($item->is_excluded_from_cfdi)<span class="badge bg-secondary">Excluir</span>
                                @else<span class="badge bg-info">Objeto {{ $item->sat_tax_object ?: $taxGroup->sat_tax_object ?: '—' }}</span>@endif
                                @if (! $item->is_active)<div><span class="badge bg-secondary mt-1">Inactivo</span></div>@endif
                            </td>
                            <td class="text-end">
                                @if ($item->is_active)
                                    <button type="submit" form="deactivate-item-{{ $item->id }}" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Desactivar este componente? Se conservará para historial.')">Desactivar</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Este grupo no tiene componentes en One Goal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</form>

@foreach ($taxGroup->items->where('is_active', true) as $item)
    <form id="deactivate-item-{{ $item->id }}" method="POST" action="{{ route('tax-groups.items.deactivate', [$taxGroup, $item]) }}" class="d-none">@csrf</form>
@endforeach

<div class="card border-0 shadow-sm mt-3">
    <div class="card-body border-bottom"><h6 class="mb-0">Agregar componente</h6></div>
    <form method="POST" action="{{ route('tax-groups.items.store', $taxGroup) }}" class="card-body row g-3 align-items-end">@csrf
        <div class="col-md-5"><label class="form-label">Impuesto simple</label><select name="tax_code_id" required class="form-select">@foreach($taxCodes as $code)<option value="{{ $code->id }}">{{ $code->name }} · {{ number_format((float) $code->rate, 4) }}{{ $code->calculation_type === 'percentage' ? '%' : '' }}</option>@endforeach</select></div>
        <div class="col-md-5"><label class="form-label">Cuenta contable</label><select name="ledger_account_id" class="form-select"><option value="">Sin cuenta</option>@foreach($ledgerAccounts as $account)<option value="{{ $account->id }}">{{ $account->display_label }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Agregar</button></div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('.account-select').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $(document.body) });
    });
</script>
@endpush
