@extends('layouts.zircos')
@section('title','Detalle del movimiento')
@section('content')
@include('budget_movements.workflow._styles')
@php
    $movement = $budgetMovement;
    $typeLabels = ['AMPLIACION' => 'Ampliación', 'TRANSFERENCIA' => 'Transferencia', 'REDUCCION' => 'Reducción'];
    $statusLabels = ['PENDIENTE_ORIGEN' => ['Esperando validación del origen', ''], 'PENDIENTE_DIRECCION' => ['Esperando Dirección General', ''], 'DEVUELTO' => ['Devuelta para corrección', 'warn'], 'APROBADO' => ['Aprobada y aplicada', 'ok'], 'RECHAZADO' => ['Rechazada', 'danger']];
    [$statusLabel, $statusTone] = $statusLabels[$movement->status] ?? [$movement->status, ''];
    $detailLabels = ['ORIGEN' => 'Sale de', 'DESTINO' => 'Llega a', 'AJUSTE' => $movement->movement_type === 'REDUCCION' ? 'Se reduce' : 'Se amplía'];
    $decisionLabels = [
        'ENVIADO' => 'Envió la solicitud',
        'ORIGEN.APROBADO' => 'Validó la salida de presupuesto del centro origen',
        'DEVUELTO' => 'Devolvió la solicitud para corrección',
        'DIRECCION.APROBADO' => 'Autorizó y aplicó el movimiento',
        'RECHAZADO' => 'Rechazó la solicitud',
    ];

    // Pasos del flujo: done | current | returned | failed | pending
    $status = $movement->status;
    $steps = [['Enviada', 'done']];
    if ($movement->isTransfer()) {
        $steps[] = ['Validación del origen', match ($status) { 'PENDIENTE_ORIGEN' => 'current', 'DEVUELTO' => 'returned', default => 'done' }];
    }
    $steps[] = ['Dirección General', match ($status) { 'PENDIENTE_DIRECCION' => 'current', 'APROBADO' => 'done', 'RECHAZADO' => 'failed', default => 'pending' }];
    $steps[] = ['Aplicada al presupuesto', $status === 'APROBADO' ? 'done' : 'pending'];
    $stepIcons = ['done' => 'ti-check', 'current' => 'ti-clock', 'returned' => 'ti-arrow-back-up', 'failed' => 'ti-x', 'pending' => 'ti-circle'];
    $r = $responsibility;
@endphp
<div class="bm-shell">
    <header class="bm-hero">
        <div>
            <p class="bm-hero-meta">Solicitud #{{ $movement->id }} · {{ $movement->movement_date->format('d/m/Y') }}</p>
            <h1>{{ $typeLabels[$movement->movement_type] ?? $movement->movement_type }} por ${{ number_format($movement->total_amount, 2) }}</h1>
            <span class="bm-status {{ $statusTone }}">{{ $statusLabel }}</span>
            <ol class="bm-steps" aria-label="Avance de la solicitud">
                @foreach($steps as [$stepLabel, $stepState])
                    <li class="is-{{ $stepState }}" @if($stepState === 'current') aria-current="step" @endif><i class="ti {{ $stepIcons[$stepState] }}" aria-hidden="true"></i>{{ $stepLabel }}</li>
                @endforeach
            </ol>
        </div>
        <a class="btn btn-light" href="{{ route('budget_movements.index') }}">Volver a la bandeja</a>
    </header>

    {{-- ¿A quién le toca? --}}
    <section class="bm-card bm-owner bm-owner-{{ $r['tone'] }}" aria-labelledby="bmOwnerTitle">
        <h2 id="bmOwnerTitle">{{ $r['title'] }}</h2>
        @if($r['verb'] && $r['people'])<p class="bm-owner-verb">{{ $r['verb'] }}:</p>@endif
        @if($r['people'])
            <ul class="bm-people">
                @foreach($r['people'] as $p)
                    <li>
                        <span class="bm-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($p['name'], 0, 1)) }}</span>
                        <div>
                            <strong>{{ $p['name'] }}</strong>@if($p['is_you']) <span class="badge text-bg-primary">Tú</span>@endif
                            <span class="d-block bm-person-role">{{ $p['role'] }}</span>
                            @if($p['note'])<small class="d-block text-muted">{{ $p['note'] }}</small>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        @if(!empty($r['also']))<p class="bm-owner-next mb-0"><i class="ti ti-shield-check" aria-hidden="true"></i> {{ $r['also'] }}</p>@endif
        @if($r['alert'])<div class="alert alert-warning mb-0 mt-2"><i class="ti ti-alert-triangle" aria-hidden="true"></i> {{ $r['alert'] }}</div>@endif
        @if(!empty($r['reason']) && $r['reason']['text'])
            <blockquote class="bm-reason">
                <p class="mb-1">“{{ $r['reason']['text'] }}”</p>
                <footer class="text-muted small">{{ $r['reason']['by'] }} · {{ $r['reason']['at']->format('d/m/Y H:i') }}</footer>
            </blockquote>
        @endif
        @if($r['next'])<p class="bm-owner-next mb-0"><i class="ti ti-arrow-right" aria-hidden="true"></i> {{ $r['next'] }}</p>@endif
    </section>

    {{-- Acciones para quien le toca --}}
    @if($movement->created_by === auth()->id() && $movement->isReturned())
        <section class="bm-card bm-action">
            <h2>Te toca corregirla</h2>
            <p class="text-muted">Ajusta lo que te pidieron y vuelve a enviarla; regresará al mismo flujo de autorización.</p>
            <a class="btn btn-primary" href="{{ route('budget_movements.edit', $movement) }}">Corregir y reenviar</a>
        </section>
    @endif
    @if($canOriginApprove)
        <section class="bm-card bm-action">
            @php($ownsOrigin = $movement->details->firstWhere('detail_type', 'ORIGEN')?->costCenter?->responsible_user_id === auth()->id())
            <h2>{{ $ownsOrigin ? 'Te toca validar la salida de presupuesto de tu centro' : 'Puedes validar la salida de presupuesto del centro origen como Superadministrador' }}</h2>
            <p class="text-muted">Si la aceptas pasa a Dirección General; si algo no cuadra, devuélvela con el motivo para que el solicitante la corrija.</p>
            <div class="d-flex flex-wrap gap-2 align-items-start">
                <form method="POST" action="{{ route('budget_movements.origin-approve', $movement) }}">@csrf<button class="btn btn-success">Aceptar transferencia</button></form>
                <details class="bm-decision">
                    <summary class="btn btn-outline-danger">Devolver para corrección</summary>
                    <form method="POST" action="{{ route('budget_movements.return', $movement) }}" class="mt-2">@csrf
                        <label class="form-label" for="returnComments">¿Qué debe corregirse?</label>
                        <textarea required minlength="10" maxlength="1000" class="form-control mb-2" id="returnComments" name="comments" rows="3"></textarea>
                        <button class="btn btn-danger">Devolver</button>
                    </form>
                </details>
            </div>
        </section>
    @endif
    @if($canExecutiveApprove)
        <section class="bm-card bm-action">
            <h2>Te toca autorizar este movimiento</h2>
            <p class="text-muted">Al aprobar se actualiza el presupuesto de inmediato y no se puede deshacer; solo se corrige con una reversa.</p>
            <div class="d-flex flex-wrap gap-2 align-items-start">
                <form method="POST" action="{{ route('budget_movements.approve', $movement) }}">@csrf<button class="btn btn-success">Aprobar y aplicar</button></form>
                <details class="bm-decision">
                    <summary class="btn btn-outline-danger">Rechazar</summary>
                    <form method="POST" action="{{ route('budget_movements.reject', $movement) }}" class="mt-2">@csrf
                        <label class="form-label" for="rejectComments">Motivo del rechazo</label>
                        <textarea required minlength="10" maxlength="1000" class="form-control mb-2" id="rejectComments" name="comments" rows="3"></textarea>
                        <button class="btn btn-danger">Confirmar rechazo</button>
                    </form>
                </details>
            </div>
        </section>
    @endif

    {{-- Partidas --}}
    <section class="bm-card">
        <h2>Partidas afectadas</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th scope="col">Movimiento</th><th scope="col">Centro de costo</th><th scope="col">Mes</th><th scope="col">Cuenta / subcuenta</th><th scope="col" class="text-end">Monto</th></tr></thead>
                <tbody>
                    @foreach($movement->details as $detail)
                        <tr>
                            <td>{{ $detailLabels[$detail->detail_type] ?? $detail->detail_type }}</td>
                            <td>{{ $detail->costCenter?->code }} · {{ $detail->costCenter?->name }}<small class="d-block text-muted">Responsable: {{ $detail->costCenter?->responsible?->name ?? 'sin asignar' }}</small></td>
                            <td>{{ $detail->month_name }} {{ $movement->fiscal_year }}</td>
                            <td>{{ $detail->expenseCategory?->name }}<small class="d-block text-muted">{{ $detail->budgetCedula?->name }}</small></td>
                            <td class="text-end bm-amount {{ $detail->amount < 0 ? 'text-danger' : 'text-success' }}">{{ $detail->amount < 0 ? '−' : '+' }}${{ number_format(abs($detail->amount), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Datos de la solicitud --}}
    <section class="bm-card">
        <h2>Datos de la solicitud</h2>
        <div class="bm-detail-grid">
            <div class="bm-fact"><span>Solicitante</span><strong>{{ $movement->creator?->name }}</strong></div>
            <div class="bm-fact"><span>Fecha de la solicitud</span><strong>{{ $movement->movement_date->format('d/m/Y') }}</strong></div>
            <div class="bm-fact"><span>Año fiscal</span><strong>{{ $movement->fiscal_year }}</strong></div>
            <div class="bm-fact"><span>Monto</span><strong>${{ number_format($movement->total_amount, 2) }} MXN</strong></div>
        </div>
        @if($movement->reversalOf)<p class="mt-3 mb-0"><span class="badge text-bg-warning">Reversa del movimiento #{{ $movement->reversal_of_id }}</span> <a href="{{ route('budget_movements.show', $movement->reversalOf) }}">Ver original</a></p>@endif
        @if($movement->reversals->isNotEmpty())<p class="mt-3 mb-0"><span class="badge text-bg-secondary">Tiene reversa</span> @foreach($movement->reversals as $reversal)<a href="{{ route('budget_movements.show', $reversal) }}">#{{ $reversal->id }}</a>@endforeach</p>@endif
        <h3 class="bm-subtitle">Justificación</h3>
        <p class="mb-0 bm-prose">{{ $movement->justification }}</p>
        @if($movement->attachments->isNotEmpty())
            <h3 class="bm-subtitle">Documentos de soporte</h3>
            <ul class="bm-files">
                @foreach($movement->attachments as $attachment)
                    <li><i class="ti ti-paperclip" aria-hidden="true"></i> <a href="{{ route('budget_movements.attachments.download', $attachment) }}">{{ $attachment->original_name }}</a>
                        <small class="text-muted">{{ $attachment->uploader?->name }} · {{ $attachment->created_at?->format('d/m/Y') }} · <span title="SHA-256: {{ $attachment->sha256 }}">huella {{ substr($attachment->sha256, 0, 10) }}…</span></small></li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Trazabilidad --}}
    <section class="bm-card">
        <h2>Historial</h2>
        <div class="bm-timeline">
            @forelse($movement->decisions->sortBy('created_at') as $decision)
                <div class="bm-timeline-item">
                    <strong>{{ $decision->actor?->name ?? 'Sistema' }}</strong> <span>{{ $decisionLabels[$decision->stage.'.'.$decision->action] ?? $decisionLabels[$decision->action] ?? $decision->action }}</span>
                    <small class="d-block text-muted">{{ $decision->created_at->format('d/m/Y H:i') }}</small>
                    @if($decision->comments)<p class="mb-0 mt-1 bm-prose">{{ $decision->comments }}</p>@endif
                </div>
            @empty
                <div class="text-muted">Aún no hay movimientos en el historial.</div>
            @endforelse
        </div>
    </section>

    @if($canReverse)
        <section class="bm-card">
            <h2>Registrar movimiento inverso</h2>
            <p class="text-muted">La reversa sigue el flujo de autorización habitual y conserva este movimiento como referencia.</p>
            <form method="POST" action="{{ route('budget_movements.reverse', $movement) }}">@csrf
                <label class="form-label" for="reverseJustification">Motivo de la reversa</label>
                <textarea name="justification" id="reverseJustification" class="form-control mb-2" rows="2" required minlength="10" maxlength="1000"></textarea>
                <button class="btn btn-outline-danger">Crear reversa</button>
            </form>
        </section>
    @endif
</div>
@endsection
