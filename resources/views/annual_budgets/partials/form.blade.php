<!-- ===== CENTRO DE COSTO ===== -->
<div class="mb-3">
    <label for="cost_center_id" class="form-label">
        Centro de Costo
        <span class="text-danger">*</span>
    </label>
    <select name="cost_center_id" id="cost_center_id" class="@error('cost_center_id') is-invalid @enderror form-select"
        {{ $action === 'edit' ? 'disabled' : 'required' }}>
        <option value="">-- Selecciona un centro de costo --</option>
        @forelse ($costCenters as $center)
        <option value="{{ $center->id }}" data-company="{{ $center->company?->name ?? '' }}"
            data-category="{{ $center->category?->name ?? '' }}"
            {{ old('cost_center_id', $annual_budget->cost_center_id ?? '') == $center->id ? 'selected' : '' }}>
            [{{ $center->code }}] {{ $center->name }}
            <small class="text-muted">{{ $center->company?->name ?? '—' }}</small>
        </option>
        @empty
        <option disabled>No hay centros de costo ANNUAL activos</option>
        @endforelse
    </select>

    @if ($action === 'edit')
    <input type="hidden" name="cost_center_id" value="{{ $annual_budget->cost_center_id }}">
    <small class="text-muted d-block mt-1">
        <i class="ti ti-info-circle me-1"></i>
        No se puede cambiar el centro de costo una vez creado.
    </small>
    @endif

    @error('cost_center_id')
    <div class="invalid-feedback d-block">
        {{ $message }}
    </div>
    @enderror
</div>

<!-- ===== INFORMACIÓN DEL CENTRO (mostrar en edit) ===== -->
@if ($action === 'edit' && $annual_budget->costCenter)
<div class="alert alert-light mb-3 border">
    <div class="row g-3 small">
        <div class="col-md-4">
            <div>
                <span class="text-muted d-block mb-1">Centro de Costo</span>
                <strong>[{{ $annual_budget->costCenter->code }}] {{ $annual_budget->costCenter->name }}</strong>
            </div>
        </div>
        <div class="col-md-4">
            <div>
                <span class="text-muted d-block mb-1">Empresa</span>
                <strong>{{ $annual_budget->costCenter->company?->name ?? '—' }}</strong>
            </div>
        </div>
        <div class="col-md-4">
            <div>
                <span class="text-muted d-block mb-1">Categoría</span>
                <strong>{{ $annual_budget->costCenter->category?->name ?? '—' }}</strong>
            </div>
        </div>
    </div>
</div>
@endif

<!-- ===== AÑO FISCAL ===== -->
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label for="fiscal_year" class="form-label">
            Año Fiscal
            <span class="text-danger">*</span>
        </label>
        <input type="number" name="fiscal_year" id="fiscal_year"
            class="form-control @error('fiscal_year') is-invalid @enderror" min="{{ date('Y') - 1 }}"
            max="{{ date('Y') + 10 }}" value="{{ old('fiscal_year', $annual_budget->fiscal_year ?? date('Y')) }}"
            {{ $action === 'edit' ? 'disabled' : 'required' }} placeholder="2025">

        @if ($action === 'edit')
        <input type="hidden" name="fiscal_year" value="{{ $annual_budget->fiscal_year }}">
        @endif

        @error('fiscal_year')
        <div class="invalid-feedback d-block">
            {{ $message }}
        </div>
        @enderror
        <small class="text-muted d-block mt-1">
            Rango permitido: {{ date('Y') - 1 }} a {{ date('Y') + 10 }}
        </small>
    </div>

    <!-- ===== MONTO TOTAL ANUAL ===== -->
    <div class="col-md-6">
        <label for="total_annual_amount" class="form-label">
            Monto Total Anual
            <span class="text-danger">*</span>
        </label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="text" id="total_annual_amount_display"
                class="form-control @error('total_annual_amount') is-invalid @enderror"
                value="{{ old('total_annual_amount', $annual_budget->total_annual_amount ?? '') }}"
                placeholder="0.00" inputmode="decimal" autocomplete="off">
            <input type="hidden" name="total_annual_amount" id="total_annual_amount"
                value="{{ old('total_annual_amount', $annual_budget->total_annual_amount ?? '') }}">
        </div>
        @error('total_annual_amount')
        <div class="invalid-feedback d-block">
            {{ $message }}
        </div>
        @enderror
        <small class="text-muted d-block mt-1">
            <i class="ti ti-info-circle me-1"></i>
            Este monto se distribuirá mensualmente entre 7 cuentas.
        </small>
    </div>
</div>

<!-- ===== RESUMEN VISUAL ===== -->
<div class="card bg-light mb-3 border-0">
    <div class="card-body">
        <h6 class="card-title small mb-3">Distribución Estimada</h6>
        <div class="row g-3 text-center">
            <div class="col-4">
                <div>
                    <small class="text-muted d-block mb-1">Por Mes</small>
                    <h6 class="mb-0" id="montoMensual">$0.00</h6>
                    <small class="text-muted">(÷ 12 meses)</small>
                </div>
            </div>
            <div class="col-4">
                <div>
                    <small class="text-muted d-block mb-1">Por Cuenta/Mes</small>
                    <h6 class="mb-0" id="montoCategoria">$0.00</h6>
                    <small class="text-muted">(promedio)</small>
                </div>
            </div>
            <div class="col-4">
                <div>
                    <small class="text-muted d-block mb-1">Total Distribuido</small>
                    <h6 class="mb-0" id="montoTotal">$0.00</h6>
                    <small class="text-muted">(12 × 7)</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== BOTONES ===== -->
<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ti ti-device-floppy me-1"></i>
        {{ $action === 'create' ? 'Crear Presupuesto' : 'Guardar Cambios' }}
    </button>
    <a href="{{ route('annual_budgets.index') }}" class="btn btn-outline-secondary">
        <i class="ti ti-x me-1"></i>
        Cancelar
    </a>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const displayInput = document.getElementById('total_annual_amount_display');
        const hiddenInput  = document.getElementById('total_annual_amount');
        const montoMensual   = document.getElementById('montoMensual');
        const montoCategoria = document.getElementById('montoCategoria');
        const montoTotal     = document.getElementById('montoTotal');

        const formatCurrency = (value) =>
            new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN',
                minimumFractionDigits: 2
            }).format(value);

        // Formatea el número con separador de miles pero sin símbolo de moneda
        const formatDisplay = (value) =>
            new Intl.NumberFormat('es-MX', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            }).format(value);

        // Limpia el string del display para obtener un float
        const parseDisplay = (str) => {
            // Elimina todo excepto dígitos, punto y coma (separadores de es-MX)
            // En es-MX el separador de miles es ',' y el decimal es '.'
            const clean = str.replace(/[^0-9.]/g, '');
            return parseFloat(clean) || 0;
        };

        const updateDistribution = (value) => {
            const monthly    = value / 12;
            const byCategory = monthly / 7;
            montoMensual.textContent   = formatCurrency(monthly);
            montoCategoria.textContent = formatCurrency(byCategory);
            montoTotal.textContent     = formatCurrency(value);
        };

        // Mientras el usuario escribe: formatear y sincronizar al hidden
        displayInput.addEventListener('input', function() {
            const raw = parseDisplay(this.value);
            hiddenInput.value = raw || '';
            updateDistribution(raw);
        });

        // Al perder el foco: re-formatear con separador de miles
        displayInput.addEventListener('blur', function() {
            const raw = parseDisplay(this.value);
            if (raw) {
                this.value = formatDisplay(raw);
            }
        });

        // Al obtener el foco: mostrar el número limpio para facilitar edición
        displayInput.addEventListener('focus', function() {
            const raw = parseDisplay(this.value);
            this.value = raw ? raw.toString() : '';
        });

        // Antes del submit: asegurarse que el hidden tiene el valor correcto
        displayInput.closest('form')?.addEventListener('submit', function() {
            hiddenInput.value = parseDisplay(displayInput.value) || '';
        });

        // Inicializar: formatear el valor que venga del servidor
        const initial = parseFloat(hiddenInput.value) || 0;
        if (initial) {
            displayInput.value = formatDisplay(initial);
        }
        updateDistribution(initial);
    });

    $(document).ready(function() {
        $('#cost_center_id').select2({
            theme: 'bootstrap-5',
            placeholder: 'Buscar centro de costo...',
            allowClear: true,
            width: '100%'
        });
    });
</script>
@endpush