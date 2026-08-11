@php($canViewAuthorization = auth()->user()?->hasRole('superadmin') ?? false)

<section id="analysisStep" class="tg-followup-flow" aria-labelledby="followup-title">
    <header class="tg-followup-header">
        <div>
            <span class="tg-section-kicker">Seguimiento</span>
            <h3 id="followup-title">Respuestas y comparativos</h3>
            <p>Identifica rápidamente qué proveedores ya respondieron y abre el comparativo cuando una solicitud esté completa.</p>
        </div>
        <div class="tg-followup-metrics">
            <div><span>RFQs activas</span><strong id="totalRfqsStep5">0</strong></div>
            <div><span>Con respuesta</span><strong class="text-success" id="completedRfqsStep5">0</strong></div>
        </div>
    </header>

    <div class="tg-followup-notice">
        <i class="ti ti-bulb"></i>
        <span>El progreso se actualiza al cargar la tabla. Abre el detalle para revisar respuestas o el cuadro comparativo para tomar una decisión.</span>
    </div>

    <div class="tg-table-shell">
        <div class="tg-table-heading">
            <div><span class="tg-section-kicker">Tablero de respuestas</span><h4>Estado por solicitud</h4></div>
            <span class="tg-table-hint">Actualización automática</span>
        </div>
        <div class="table-responsive">
            <table id="rfq-analysis-table" class="table align-middle mb-0 w-100">
                <thead>
                    <tr>
                        <th>Folio RFQ</th>
                        <th>Grupo / título</th>
                        <th>Progreso de respuestas</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        @if($canViewAuthorization)<th>Autorización</th>@endif
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="infoAjaxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" id="modal-loader-content">
            <div class="p-5 text-center">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Cargando información detallada...</p>
            </div>
        </div>
    </div>
</div>

<style>
    .tg-followup-header { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; margin-bottom:1rem; }
    .tg-followup-header h3 { margin:.2rem 0; font-size:1.1rem; }
    .tg-followup-header p { margin:0; color:#718096; font-size:.85rem; }
    .tg-followup-metrics { display:flex; gap:.6rem; }
    .tg-followup-metrics > div { min-width:105px; padding:.7rem .85rem; border:1px solid #e2e9f0; border-radius:.65rem; background:#fff; text-align:center; }
    .tg-followup-metrics span, .tg-followup-metrics strong { display:block; }
    .tg-followup-metrics span { color:#718096; font-size:.68rem; }
    .tg-followup-metrics strong { font-size:1.15rem; }
    .tg-followup-notice { display:flex; gap:.6rem; align-items:center; padding:.75rem .9rem; margin-bottom:1rem; border-radius:.65rem; color:#526274; background:#f7fbff; font-size:.8rem; }
    .tg-followup-notice i { color:#188ae2; font-size:1.1rem; }
    .tg-followup-flow #rfq-analysis-table thead th { padding:.8rem 1rem; background:#f7fbff; }
    .tg-followup-flow #rfq-analysis-table tbody td { padding:.8rem 1rem; }
    .tg-authorization-cell { min-width:185px; }
    .tg-authorization-label { display:block; color:#718096; font-size:.67rem; line-height:1.15; text-transform:uppercase; letter-spacing:.035em; }
    .tg-authorization-cell strong { display:block; margin-top:.15rem; color:#27364a; font-size:.8rem; }
    .tg-authorization-detail { display:block; margin-top:.1rem; color:#718096; font-size:.72rem; }
    .tg-authorization-state { display:inline-flex; align-items:center; gap:.25rem; margin-top:.28rem; padding:.15rem .38rem; border-radius:999px; font-size:.67rem; font-weight:700; }
    .tg-authorization-state.pending { color:#1367a8; background:#eaf5ff; }
    .tg-authorization-state.approved { color:#177b42; background:#eaf8ef; }
    .tg-authorization-state.rejected, .tg-authorization-state.warning { color:#a35c00; background:#fff4df; }
    .tg-authorization-state.waiting { color:#66768a; background:#f0f3f6; }
    @media (max-width:767.98px) { .tg-followup-header { flex-direction:column; } .tg-followup-metrics { width:100%; } .tg-followup-metrics > div { flex:1; } }
</style>
