@extends('layouts.zircos')

@section('title', 'Detalle de Distribución')

@section('page.title', 'Detalle de Distribución')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Distribución mensual por subcuenta</h5>
        <a href="{{ route('annual_budgets.show', $distribution->annualBudget->id) }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-arrow-left me-1"></i> Volver
        </a>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Centro de costo</dt>
            <dd class="col-sm-9">[{{ $distribution->annualBudget->costCenter?->code }}] {{ $distribution->annualBudget->costCenter?->name }}</dd>

            <dt class="col-sm-3">Cuenta</dt>
            <dd class="col-sm-9">[{{ $distribution->expenseCategory?->code }}] {{ $distribution->expenseCategory?->name }}</dd>

            <dt class="col-sm-3">Subcuenta</dt>
            <dd class="col-sm-9">{{ $distribution->budgetCedula?->name ?? '—' }}</dd>

            <dt class="col-sm-3">Mes</dt>
            <dd class="col-sm-9">{{ $distribution->month_label }}</dd>

            <dt class="col-sm-3">Asignado</dt>
            <dd class="col-sm-9">${{ number_format($distribution->assigned_amount, 2) }}</dd>

            <dt class="col-sm-3">Consumido</dt>
            <dd class="col-sm-9">${{ number_format($distribution->consumed_amount, 2) }}</dd>

            <dt class="col-sm-3">Comprometido</dt>
            <dd class="col-sm-9">${{ number_format($distribution->committed_amount, 2) }}</dd>

            <dt class="col-sm-3">Disponible</dt>
            <dd class="col-sm-9">${{ number_format($distribution->getAvailableAmount(), 2) }}</dd>
        </dl>
    </div>
</div>
@endsection
