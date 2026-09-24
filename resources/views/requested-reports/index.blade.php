@extends('layouts.zircos')

@section('title', 'Reportes solicitados')
@section('page.title', 'Reportes solicitados')

@push('styles')
<style>
.reports-hero,.report-card,.rr-summary{border:1px solid #e2e9f0;border-radius:.85rem;background:#fff;box-shadow:0 6px 20px rgba(28,80,120,.05)}
.reports-hero{padding:1.25rem 1.4rem;margin-bottom:1rem;background:#f7fbff}
.reports-kicker{color:#188ae2;font-weight:800;font-size:.72rem;letter-spacing:.07em;text-transform:uppercase}
.report-icon{width:2.5rem;height:2.5rem;border-radius:.7rem;display:grid;place-items:center;background:#eaf6ff;color:#188ae2;font-size:1.25rem;flex-shrink:0}
.rr-summary{display:flex;flex-wrap:wrap;gap:1.5rem;padding:.9rem 1.25rem;margin-bottom:1rem}
.rr-summary-item strong{display:block;font-size:1.25rem;color:#24364b;line-height:1.2}
.rr-summary-item span{font-size:.75rem;color:#718096}
.rr-group-title{display:flex;align-items:center;gap:.5rem}
.rr-group-title i{color:#188ae2;font-size:1rem}
.report-card{height:100%;padding:1rem;display:flex;flex-direction:column;transition:box-shadow .18s}
.report-card:hover{box-shadow:0 12px 26px rgba(24,138,226,.13)}
.rr-code{font-weight:800;color:#188ae2;font-size:.78rem;letter-spacing:.04em}
.report-card h6{color:#24364b;line-height:1.35}
.rr-purpose{font-size:.8rem;color:#4a5568;margin:.45rem 0 .7rem}
.rr-meta{font-size:.75rem;color:#718096;margin:0 0 .7rem;display:grid;grid-template-columns:auto 1fr;gap:.2rem .6rem}
.rr-meta dt{font-weight:600;color:#4a5568}
.rr-meta dd{margin:0}
.rr-fields-title{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#718096;margin-bottom:.3rem}
.rr-fields{font-size:.77rem;color:#4a5568;padding-left:1rem;margin-bottom:.8rem}
.rr-fields li{margin-bottom:.15rem}
.rr-footer{margin-top:auto;padding-top:.7rem;border-top:1px dashed #e2e9f0;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:.5rem;font-size:.75rem}
@media (prefers-reduced-motion:reduce){.report-card{transition:none}}
</style>
@endpush

@section('content')
<div class="reports-hero d-flex flex-wrap align-items-center gap-3">
    <div class="report-icon"><i class="ti ti-clipboard-list"></i></div>
    <div class="flex-grow-1">
        <span class="reports-kicker">Sección temporal</span>
        <h5 class="mb-1">Reportes solicitados por Contabilidad y Finanzas</h5>
        <p class="mb-0 text-muted small">Catálogo de los {{ $summary['total'] }} reportes requeridos. Cada tarjeta describe qué pregunta responde, quién lo usa y qué información incluirá. Se irán construyendo uno a uno.</p>
    </div>
    <div class="btn-group btn-group-sm" role="group" aria-label="Ordenar reportes">
        <a class="btn {{ $order === 'domain' ? 'btn-primary' : 'btn-light' }}" href="{{ route('requested-reports.index') }}">Por dominio</a>
        <a class="btn {{ $order === 'feasibility' ? 'btn-primary' : 'btn-light' }}" href="{{ route('requested-reports.index', ['order' => 'feasibility']) }}">Por factibilidad</a>
    </div>
</div>

<div class="rr-summary" aria-label="Resumen de factibilidad">
    <div class="rr-summary-item"><strong>{{ $summary['total'] }}</strong><span>Reportes solicitados</span></div>
    <div class="rr-summary-item"><strong class="text-success">{{ $summary['levels']['Factibilidad alta'] ?? 0 }}</strong><span>Factibilidad alta</span></div>
    <div class="rr-summary-item"><strong class="text-warning">{{ $summary['levels']['Factibilidad media'] ?? 0 }}</strong><span>Factibilidad media</span></div>
    <div class="rr-summary-item"><strong class="text-danger">{{ $summary['levels']['Factibilidad baja'] ?? 0 }}</strong><span>Factibilidad baja</span></div>
    <div class="rr-summary-item"><strong>{{ $summary['with_base'] }}</strong><span>Con base en Reportería</span></div>
</div>

@foreach($groups as $groupKey => $items)
    <h6 class="rr-group-title text-uppercase text-muted fs-12 mt-4 mb-2">
        @if($order === 'domain')
            <i class="ti {{ $domains[$groupKey]['icon'] }}"></i>{{ $groupKey }}. {{ $domains[$groupKey]['name'] }}
        @else
            {{ $groupKey }} <span class="fw-normal text-lowercase">· {{ $items->first()['level']['hint'] }}</span>
        @endif
    </h6>
    <div class="row g-3">
        @foreach($items as $report)
            <div class="col-md-6 col-xl-4">
                <article class="report-card" data-requested-report="{{ $report['code'] }}">
                    <div class="d-flex gap-3">
                        <div class="report-icon"><i class="ti {{ $domains[$report['domain']]['icon'] }}"></i></div>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                                <span class="rr-code">{{ $report['code'] }}</span>
                                <span class="badge bg-light text-muted">Fase {{ $report['phase'] }}</span>
                                <span class="badge bg-{{ $report['level']['class'] }}-subtle text-{{ $report['level']['class'] }}" title="{{ $report['level']['hint'] }}">#{{ $report['feasibility'] }} · {{ $report['level']['label'] }}</span>
                            </div>
                            <h6 class="mb-0">{{ $report['name'] }}</h6>
                        </div>
                    </div>

                    <p class="rr-purpose">{{ $report['purpose'] }}</p>

                    <dl class="rr-meta">
                        <dt>Para</dt><dd>{{ $report['consumers'] }}</dd>
                        <dt>Frecuencia</dt><dd>{{ $report['frequency'] }}</dd>
                        <dt>Una fila =</dt><dd>{{ $report['granularity'] }}</dd>
                    </dl>

                    <div class="rr-fields-title">Qué incluirá</div>
                    <ul class="rr-fields">
                        @foreach($report['fields'] as $field)
                            <li>{{ $field }}</li>
                        @endforeach
                    </ul>

                    <div class="rr-footer">
                        <span class="text-muted"><i class="ti ti-hourglass me-1"></i>Pendiente de construir</span>
                        @if($report['existing_title'])
                            <a class="text-primary fw-semibold text-decoration-none" href="{{ route('reports.show', $report['existing_report']) }}" title="Reporte actual que ya cubre una parte">
                                Base: {{ $report['existing_title'] }} <i class="ti ti-arrow-right"></i>
                            </a>
                        @endif
                    </div>
                </article>
            </div>
        @endforeach
    </div>
@endforeach
@endsection
