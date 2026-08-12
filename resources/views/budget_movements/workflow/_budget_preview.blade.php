<section class="bm-budget-preview" data-budget-preview="{{ $context }}" aria-live="polite">
    <div class="bm-budget-preview-head"><div><span class="bm-kicker">{{ $kicker }}</span><h3>{{ $title }}</h3></div><span class="bm-preview-state">Completa centro, mes, cuenta y subcuenta.</span></div>
    <div class="bm-preview-values"><div><span>Asignado actual</span><strong data-budget-value="assigned">—</strong></div><div><span>Disponible actual</span><strong data-budget-value="available">—</strong></div><div><span>{{ $movementLabel }}</span><strong data-budget-value="movement">—</strong></div><div class="bm-preview-projected"><span>Disponible después</span><strong data-budget-value="projected">—</strong></div></div>
    <p class="bm-preview-note mb-0">Este cálculo es informativo; el saldo se valida nuevamente al autorizar.</p>
</section>
