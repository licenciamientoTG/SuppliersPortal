@extends('layouts.zircos')

@section('title', 'Reportería')
@section('page.title', 'Reportería')

@push('styles')
<style>
.reports-hero,.report-card,.reports-month-chart{border:1px solid #e2e9f0;border-radius:.85rem;background:#fff;box-shadow:0 6px 20px rgba(28,80,120,.05)}.reports-hero{padding:1.25rem 1.4rem;margin-bottom:1rem;background:#f7fbff}.reports-kicker{color:#188ae2;font-weight:800;font-size:.72rem;letter-spacing:.07em;text-transform:uppercase}.reports-month-chart{padding:1.1rem 1.25rem;margin:1rem 0}.reports-chart-head{display:flex;justify-content:space-between;align-items:start;gap:1rem;margin-bottom:.5rem}.reports-chart-title{font-size:1rem;font-weight:700;color:#24364b}.reports-chart-subtitle{font-size:.78rem;color:#718096}.reports-chart-total{font-weight:800;color:#188ae2;text-align:right}.reports-chart-canvas{min-height:285px}.reports-chart-empty{color:#718096;font-size:.85rem;padding:.7rem 0}.report-card{height:100%;padding:1rem;transition:transform .18s,box-shadow .18s}.report-card:hover{transform:translateY(-3px);box-shadow:0 12px 26px rgba(24,138,226,.13)}.report-icon{width:2.5rem;height:2.5rem;border-radius:.7rem;display:grid;place-items:center;background:#eaf6ff;color:#188ae2;font-size:1.25rem}.report-card p{font-size:.8rem;color:#718096;margin:.45rem 0 .8rem}@media (prefers-reduced-motion:reduce){.report-card{transition:none}.report-card:hover{transform:none}}
</style>
@endpush

@section('content')
<div class="reports-hero d-flex flex-wrap align-items-center gap-3"><div class="report-icon"><i class="ti ti-chart-bar"></i></div><div><span class="reports-kicker">Control ejecutivo</span><h5 class="mb-1">Reportería integral</h5><p class="mb-0 text-muted small">Consulta el ciclo completo de requisiciones, compras, proveedores, presupuesto y contratos.</p></div></div>
<section class="reports-month-chart" aria-labelledby="monthlyStatusTitle"><div class="reports-chart-head"><div><span class="reports-kicker">Seguimiento operativo</span><h2 id="monthlyStatusTitle" class="reports-chart-title mb-1">Requisiciones por estatus y departamento</h2><p class="reports-chart-subtitle mb-0">Creadas durante {{ $monthlyStatus['period'] }}.</p></div><div class="d-flex align-items-start gap-3"><div class="reports-chart-total"><span class="d-block small text-muted fw-normal">Total del mes</span>{{ $monthlyStatus['total'] }}</div><div class="btn-group btn-group-sm" role="group" aria-label="Cambiar mes de consulta"><a class="btn btn-light" href="{{ route('reports.index', ['month' => $monthlyStatus['previous_month']]) }}" aria-label="Mes anterior" title="Mes anterior"><i class="ti ti-chevron-left"></i></a><a class="btn btn-light {{ $monthlyStatus['can_next'] ? '' : 'disabled' }}" href="{{ $monthlyStatus['can_next'] ? route('reports.index', ['month' => $monthlyStatus['next_month']]) : '#' }}" aria-label="Mes siguiente" title="Mes siguiente" @if(!$monthlyStatus['can_next']) aria-disabled="true" tabindex="-1" @endif><i class="ti ti-chevron-right"></i></a></div></div></div>@if($monthlyStatus['total'])<div id="requisitionStatusChart" class="reports-chart-canvas" aria-label="Gráfica de requisiciones por estatus y departamento"></div>@else<div class="reports-chart-empty"><i class="ti ti-chart-bar-off me-1"></i>No hay requisiciones creadas en {{ $monthlyStatus['period'] }}.</div>@endif</section>
@foreach(collect($reports)->groupBy(fn($report) => $report[1], preserveKeys: true) as $group => $items)
<h6 class="text-uppercase text-muted fs-12 mt-4 mb-2">{{ $group }}</h6><div class="row g-3">
@foreach($items as $key => $report)<div class="col-md-6 col-xl-4"><a class="report-card d-block text-reset text-decoration-none" href="{{ route('reports.show',$key) }}"><div class="d-flex gap-3"><div class="report-icon"><i class="ti {{ $report[2] }}"></i></div><div><h6 class="mb-0">{{ $report[0] }}</h6><p>{{ $report[3] }}</p><span class="text-primary small fw-semibold">Abrir reporte <i class="ti ti-arrow-right"></i></span></div></div></a></div>@endforeach
</div>@endforeach
@endsection

@push('scripts')
@if($monthlyStatus['total'])
<script src="{{ asset('assets/vendor/apexcharts/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartData = @json($monthlyStatus);
    new ApexCharts(document.querySelector('#requisitionStatusChart'), {
        chart: { type: 'bar', height: 285, stacked: true, toolbar: { show: false }, fontFamily: 'Open Sans, sans-serif' },
        series: chartData.series.map(item => ({ name: item.name, data: item.data })),
        colors: chartData.series.map(item => item.color),
        xaxis: { categories: chartData.departments, labels: { style: { colors: '#718096', fontSize: '11px' } } },
        yaxis: { labels: { style: { colors: '#718096', fontSize: '11px' } } },
        plotOptions: { bar: { horizontal: false, columnWidth: '48%', borderRadius: 4 } },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 1, colors: ['#fff'] },
        legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px', markers: { width: 9, height: 9, radius: 9 } },
        grid: { borderColor: '#edf2f6', strokeDashArray: 3 },
        tooltip: { y: { formatter: value => `${value} requisición${value === 1 ? '' : 'es'}` } },
        responsive: [{ breakpoint: 640, options: { chart: { height: 335 }, legend: { position: 'bottom' }, plotOptions: { bar: { columnWidth: '68%' } } } }]
    }).render();
});
</script>
@endif
@endpush
