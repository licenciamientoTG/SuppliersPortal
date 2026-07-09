@extends('layouts.zircos')

@section('title', 'Presupuesto Anual ' . $annual_budget->fiscal_year)

@section('page.title', 'Presupuesto ' . $annual_budget->fiscal_year)

@section('page.breadcrumbs')
<li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
<li class="breadcrumb-item"><a href="{{ route('annual_budgets.index') }}">Presupuestos Anuales</a></li>
<li class="breadcrumb-item active">{{ $annual_budget->fiscal_year }}</li>
@endsection

@section('content')
<div class="content">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <p class="text-muted mb-2">
                {{ $annual_budget->costCenter?->company?->name ?? '—' }} /
                <strong>[{{ $annual_budget->costCenter?->code ?? '—' }}]</strong>
                {{ $annual_budget->costCenter?->name ?? '—' }}
            </p>
        </div>
        <div>
            <a href="{{ route('annual_budgets.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Volver
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-start border-primary border-4">
                <div class="card-body">
                    <small class="text-muted d-block mb-1">Presupuesto Asignado</small>
                    <h4 class="mb-0">${{ number_format($summary['total_assigned'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-start border-warning border-4">
                <div class="card-body">
                    <small class="text-muted d-block mb-1">Comprometido</small>
                    <h4 class="mb-0">${{ number_format($summary['total_committed'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-start border-info border-4">
                <div class="card-body">
                    <small class="text-muted d-block mb-1">Consumido</small>
                    <h4 class="mb-0">${{ number_format($summary['total_consumed'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-start border-success border-4">
                <div class="card-body">
                    <small class="text-muted d-block mb-1">Disponible</small>
                    <h4 class="mb-0">${{ number_format($summary['total_available'], 2) }}</h4>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Información General</h6>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-4">
                <label class="form-label text-muted small">Año Fiscal</label>
                <p class="fw-semibold mb-0">{{ $annual_budget->fiscal_year }}</p>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small">Estado</label>
                <p class="mb-0">
                    @if ($annual_budget->status === 'PLANIFICACION')
                        <span class="badge bg-info text-white">En Planificación</span>
                    @elseif ($annual_budget->status === 'APROBADO')
                        <span class="badge bg-success text-white">Aprobado</span>
                    @else
                        <span class="badge bg-secondary text-white">Cerrado</span>
                    @endif
                </p>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small">% Utilización</label>
                <p class="fw-semibold mb-0">{{ number_format($summary['usage_percentage'], 1) }}%</p>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small">Creado Por</label>
                <p class="mb-0">{{ $annual_budget->createdBy?->name ?? '—' }}</p>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small">Aprobado Por</label>
                <p class="mb-0">{{ $annual_budget->approvedBy?->name ?? '—' }}</p>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small">Aprobado En</label>
                <p class="mb-0">{{ $annual_budget->approved_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Utilización del Presupuesto</h6>
    </div>
    <div class="card-body">
        @php
            $usagePercentage = $summary['usage_percentage'];
            $progressColor = match (true) {
                $usagePercentage > 90 => 'bg-danger',
                $usagePercentage > 70 => 'bg-warning',
                $usagePercentage > 50 => 'bg-info',
                default => 'bg-success',
            };
        @endphp
        <div class="progress" style="height: 25px;">
            <div class="progress-bar {{ $progressColor }}" role="progressbar" style="width: {{ $usagePercentage }}%">
                <small class="fw-semibold">{{ number_format($usagePercentage, 1) }}%</small>
            </div>
        </div>
        <div class="row g-3 mt-3">
            <div class="col-sm-6">
                <small class="text-muted">Consumido + Comprometido</small>
                <p class="fw-semibold mb-0">${{ number_format($summary['total_consumed'] + $summary['total_committed'], 2) }}</p>
            </div>
            <div class="col-sm-6">
                <small class="text-muted">Disponible para Gastar</small>
                <p class="fw-semibold mb-0">${{ number_format($summary['total_available'], 2) }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Resumen por Cuenta</h6>
        <a href="{{ route('budget_monthly_distributions.matrix', $annual_budget->id) }}" class="btn btn-sm btn-outline-primary">
            <i class="ti ti-layout-grid me-1"></i> Vista Matriz
        </a>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Cuenta</th>
                    <th class="text-end">Asignado</th>
                    <th class="text-end">Consumido</th>
                    <th class="text-end">Comprometido</th>
                    <th class="text-end">Disponible</th>
                    <th class="text-end">% Uso</th>
                    <th>Subcuentas</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categorySummaries as $row)
                    <tr>
                        <td>
                            <strong>[{{ $row['category_code'] }}]</strong> {{ $row['category_name'] }}
                        </td>
                        <td class="text-end">${{ number_format($row['assigned_amount'], 2) }}</td>
                        <td class="text-end">${{ number_format($row['consumed_amount'], 2) }}</td>
                        <td class="text-end">${{ number_format($row['committed_amount'], 2) }}</td>
                        <td class="text-end">
                            <span class="badge {{ $row['available_amount'] > 0 ? 'bg-success' : 'bg-warning text-dark' }}">
                                ${{ number_format($row['available_amount'], 2) }}
                            </span>
                        </td>
                        <td class="text-end">{{ number_format($row['usage_percentage'], 1) }}%</td>
                        <td>
                            {{ collect($row['cedulas'])->pluck('budgetCedula.name')->filter()->unique()->implode(', ') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-muted py-3 text-center">
                            <i class="ti ti-inbox me-1"></i> Sin distribuciones mensuales
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
