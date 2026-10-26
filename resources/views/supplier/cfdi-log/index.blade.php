@extends('layouts.zircos-supplier')

@section('title', 'Mis Cargas de CFDI')
@section('page.title', 'Mis Cargas de CFDI')

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Historial de intentos de carga</h5>
        <p class="text-muted small mb-0">Cada intento queda registrado, aceptado o rechazado, con el motivo exacto.</p>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="small text-muted">Intentos</div><div class="fw-semibold">{{ $kpis['total_attempts'] }}</div></div></div>
            <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="small text-muted">% rechazo 1er intento</div><div class="fw-semibold">{{ number_format($kpis['first_attempt_rejection_rate'], 1) }}%</div></div></div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Fecha/hora</th>
                        <th>UUID</th>
                        <th>Total</th>
                        <th>Resultado</th>
                        <th>Motivo</th>
                        <th>Intento</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row['attempted_at'])->format('d/m/Y H:i') }}</td>
                            <td class="fw-semibold">{{ $row['uuid'] }}</td>
                            <td>${{ number_format((float) $row['total'], 2) }} {{ $row['currency'] }}</td>
                            <td><span class="badge bg-{{ $row['result'] === 'ACEPTADO' ? 'success' : 'danger' }}">{{ $row['result'] }}</span></td>
                            <td>{{ $row['supplier_message'] }}</td>
                            <td>{{ $row['attempt_no'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Sin cargas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
