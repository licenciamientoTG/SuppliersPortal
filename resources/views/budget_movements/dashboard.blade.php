@extends('layouts.zircos')

@section('title', 'Dashboard - Movimientos Críticos')

@section('page.title', 'Dashboard - Movimientos Críticos')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('budget_movements.index') }}">Movimientos Presupuestales</a></li>
    <li class="breadcrumb-item active">Dashboard Crítico</li>
@endsection

@section('content')
<!-- Botones de Navegación -->
<div class="mb-4">
    <a href="{{ route('budget_movements.index') }}" class="btn btn-secondary">
        <i class="ti ti-arrow-left me-1"></i>
        Volver al Listado
    </a>
    <a href="{{ route('budget_movements.create') }}" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i>
        Nuevo Movimiento
    </a>
</div>

<!-- ===== ALERTAS CRÍTICAS ===== -->
<div class="row mb-4">
    <!-- Movimientos Pendientes -->
    <div class="col-md-6">
        <div class="card border-warning">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="ti ti-alert-triangle me-2"></i>
                    Movimientos Pendientes de Aprobación
                    <span class="badge bg-dark ms-2">{{ $pendingMovements->count() }}</span>
                </h5>
            </div>
            <div class="card-body">
                @if($pendingMovements->isEmpty())
                <div class="alert alert-success mb-0">
                    <i class="ti ti-circle-check me-2"></i>
                    No hay movimientos pendientes
                </div>
                @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tipo</th>
                                <th>Monto</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingMovements as $movement)
                            <tr>
                                <td><strong>#{{ $movement->id }}</strong></td>
                                <td>
                                    @if($movement->movement_type === 'TRANSFERENCIA')
                                    <span class="badge bg-info">Transferencia</span>
                                    @elseif($movement->movement_type === 'AMPLIACION')
                                    <span class="badge bg-success">Ampliación</span>
                                    @else
                                    <span class="badge bg-danger">Reducción</span>
                                    @endif
                                </td>
                                <td class="fw-bold">${{ number_format($movement->total_amount, 2) }}</td>
                                <td>
                                    <small>{{ $movement->created_at->diffForHumans() }}</small>
                                </td>
                                <td>
                                    <a href="{{ route('budget_movements.show', $movement) }}"
                                        class="btn btn-sm btn-primary"
                                        title="Ver detalles">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Presupuestos Críticos -->
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="ti ti-alert-circle me-2"></i>
                    Presupuestos Críticos/Agotados
                    <span class="badge bg-dark ms-2">{{ $criticalBudgets->count() }}</span>
                </h5>
            </div>
            <div class="card-body">
                @if($criticalBudgets->isEmpty())
                <div class="alert alert-success mb-0">
                    <i class="ti ti-circle-check me-2"></i>
                    Todos los presupuestos en buen estado
                </div>
                @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Centro de Costo</th>
                                <th>Mes</th>
                                <th>Cuenta</th>
                                <th>Disponible</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($criticalBudgets as $budget)
                            <tr>
                                <td>
                                    <strong>{{ $budget->annualBudget->costCenter->name }}</strong><br>
                                    <small class="text-muted">{{ $budget->annualBudget->costCenter->code }}</small>
                                </td>
                                <td>{{ $budget->month_label }}</td>
                                <td>{{ $budget->expenseCategory->name }}</td>
                                <td class="fw-bold">
                                    ${{ number_format($budget->getAvailableAmount(), 2) }}
                                </td>
                                <td>{!! $budget->status_label !!}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- ===== RESUMEN EJECUTIVO ===== -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="ti ti-chart-bar me-2"></i>
                    Resumen Ejecutivo - {{ now()->translatedFormat('F Y') }}
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Movimientos por Estado -->
                    <div class="col-md-6">
                        <h6 class="mb-3">Movimientos por Estado</h6>
                        <div class="row g-3">
                            <div class="col-4">
                                <div class="card bg-warning bg-opacity-10 border-warning">
                                    <div class="card-body text-center">
                                        <h3 class="mb-1">{{ $summaryByStatus['pending'] }}</h3>
                                        <small class="text-muted">Pendientes</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card bg-success bg-opacity-10 border-success">
                                    <div class="card-body text-center">
                                        <h3 class="mb-1">{{ $summaryByStatus['approved'] }}</h3>
                                        <small class="text-muted">Aprobados</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card bg-danger bg-opacity-10 border-danger">
                                    <div class="card-body text-center">
                                        <h3 class="mb-1">{{ $summaryByStatus['rejected'] }}</h3>
                                        <small class="text-muted">Rechazados</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Monto por Tipo -->
                    <div class="col-md-6">
                        <h6 class="mb-3">Monto Total por Tipo</h6>
                        <table class="table table-sm">
                            <tbody>
                                <tr>
                                    <td>
                                        <span class="badge bg-info">Transferencias</span>
                                    </td>
                                    <td class="text-end fw-bold">
                                        ${{ number_format($summaryByType['TRANSFERENCIA'], 2) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="badge bg-success">Ampliaciones</span>
                                    </td>
                                    <td class="text-end fw-bold">
                                        ${{ number_format($summaryByType['AMPLIACION'], 2) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="badge bg-danger">Reducciones</span>
                                    </td>
                                    <td class="text-end fw-bold">
                                        ${{ number_format($summaryByType['REDUCCION'], 2) }}
                                    </td>
                                </tr>
                                <tr class="border-top">
                                    <td><strong>TOTAL</strong></td>
                                    <td class="text-end fw-bold text-primary">
                                        ${{ number_format($totalAmount, 2) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== ÚLTIMAS ACTIVIDADES ===== -->
<div class="row">
    <!-- Últimos Aprobados -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="ti ti-check me-2"></i>
                    Últimos Movimientos Aprobados
                </h5>
            </div>
            <div class="card-body">
                @if($recentApproved->isEmpty())
                <p class="text-muted mb-0">No hay movimientos aprobados recientemente</p>
                @else
                <div class="list-group list-group-flush">
                    @foreach($recentApproved as $movement)
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>#{{ $movement->id }}</strong> -
                                @if($movement->movement_type === 'TRANSFERENCIA')
                                Transferencia
                                @elseif($movement->movement_type === 'AMPLIACION')
                                Ampliación
                                @else
                                Reducción
                                @endif
                                <br>
                                <small class="text-muted">
                                    Por {{ $movement->creator->name }} •
                                    Aprobado {{ $movement->approved_at->diffForHumans() }}
                                </small>
                            </div>
                            <div class="text-end">
                                <strong class="text-success">${{ number_format($movement->total_amount, 2) }}</strong>
                                <br>
                                <a href="{{ route('budget_movements.show', $movement) }}" class="btn btn-sm btn-outline-primary">
                                    Ver
                                </a>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Últimos Rechazados -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="ti ti-x me-2"></i>
                    Últimos Movimientos Rechazados
                </h5>
            </div>
            <div class="card-body">
                @if($recentRejected->isEmpty())
                <p class="text-muted mb-0">No hay movimientos rechazados recientemente</p>
                @else
                <div class="list-group list-group-flush">
                    @foreach($recentRejected as $movement)
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>#{{ $movement->id }}</strong> -
                                @if($movement->movement_type === 'TRANSFERENCIA')
                                Transferencia
                                @elseif($movement->movement_type === 'AMPLIACION')
                                Ampliación
                                @else
                                Reducción
                                @endif
                                <br>
                                <small class="text-muted">
                                    Por {{ $movement->creator->name }} •
                                    Rechazado {{ $movement->approved_at->diffForHumans() }}
                                </small>
                            </div>
                            <div class="text-end">
                                <strong class="text-danger">${{ number_format($movement->total_amount, 2) }}</strong>
                                <br>
                                <a href="{{ route('budget_movements.show', $movement) }}" class="btn btn-sm btn-outline-primary">
                                    Ver
                                </a>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .list-group-item {
        border-left: 0;
        border-right: 0;
    }

    .list-group-item:first-child {
        border-top: 0;
    }

    .list-group-item:last-child {
        border-bottom: 0;
    }
</style>
@endpush