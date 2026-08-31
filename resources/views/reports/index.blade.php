@extends('layouts.zircos')

@section('title', 'Reportería')
@section('page.title', 'Reportería')

@push('styles')
<style>
.reports-hero,.report-card{border:1px solid #e2e9f0;border-radius:.85rem;background:#fff;box-shadow:0 6px 20px rgba(28,80,120,.05)}.reports-hero{padding:1.25rem 1.4rem;margin-bottom:1rem;background:#f7fbff}.reports-kicker{color:#188ae2;font-weight:800;font-size:.72rem;letter-spacing:.07em;text-transform:uppercase}.report-card{height:100%;padding:1rem;transition:transform .18s,box-shadow .18s}.report-card:hover{transform:translateY(-3px);box-shadow:0 12px 26px rgba(24,138,226,.13)}.report-icon{width:2.5rem;height:2.5rem;border-radius:.7rem;display:grid;place-items:center;background:#eaf6ff;color:#188ae2;font-size:1.25rem}.report-card p{font-size:.8rem;color:#718096;margin:.45rem 0 .8rem}@media (prefers-reduced-motion:reduce){.report-card{transition:none}.report-card:hover{transform:none}}
</style>
@endpush

@section('content')
<div class="reports-hero d-flex flex-wrap align-items-center gap-3"><div class="report-icon"><i class="ti ti-chart-bar"></i></div><div><span class="reports-kicker">Control ejecutivo</span><h5 class="mb-1">Reportería integral</h5><p class="mb-0 text-muted small">Consulta el ciclo completo de requisiciones, compras, proveedores, presupuesto y contratos.</p></div></div>
@foreach(collect($reports)->groupBy(fn($report) => $report[1]) as $group => $items)
<h6 class="text-uppercase text-muted fs-12 mt-4 mb-2">{{ $group }}</h6><div class="row g-3">
@foreach($items as $key => $report)<div class="col-md-6 col-xl-4"><a class="report-card d-block text-reset text-decoration-none" href="{{ route('reports.show',$key) }}"><div class="d-flex gap-3"><div class="report-icon"><i class="ti {{ $report[2] }}"></i></div><div><h6 class="mb-0">{{ $report[0] }}</h6><p>Filtros, indicadores, detalle y exportación.</p><span class="text-primary small fw-semibold">Abrir reporte <i class="ti ti-arrow-right"></i></span></div></div></a></div>@endforeach
</div>@endforeach
@endsection
