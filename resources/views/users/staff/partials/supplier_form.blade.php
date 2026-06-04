@php
  /** @var \App\Models\User $user */
  $supplier = $user->supplier ?? null;
  $personTypes = \App\Support\SupplierFiscalCatalog::personTypes();
  $selectedPersonType = old('person_type', $supplier->person_type ?? '');
  $selectedTaxRegimes = collect(old('tax_regimes', $supplier->tax_regimes ?? []))
    ->map(fn ($regime) => is_array($regime) ? ($regime['code'] ?? null) : $regime)
    ->filter()
    ->values()
    ->all();
  $economicActivities = old('economic_activity', $supplier->economic_activity ?? ['']);
  if (! is_array($economicActivities) || count($economicActivities) === 0) {
    $economicActivities = [''];
  }
@endphp

<div class="modal-header">
  <h5 class="modal-title">
    <i class="ti ti-building-store me-2"></i>
    {{ $supplier ? 'Editar datos de proveedor' : 'Agregar datos de proveedor' }}
  </h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<form id="supplierForm"
      action="{{ $supplier ? route('users.supplier.update', $user) : route('users.supplier.store', $user) }}"
      method="POST"
      autocomplete="off">
  @csrf
  @if($supplier)
    @method('PUT')
  @endif

  <div class="modal-body">
    <div id="formErrors" class="alert alert-danger d-none mb-3"></div>

    <div class="border rounded p-3 mb-3">
      <h6 class="text-uppercase text-muted fw-semibold mb-3">
        <i class="ti ti-id-badge me-1"></i> Información del proveedor
      </h6>

      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Razón social</label>
          <input type="text" name="company_name" class="form-control"
                 value="{{ old('company_name', $supplier->company_name ?? '') }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">RFC</label>
          <input type="text" name="rfc" class="form-control"
                 value="{{ old('rfc', $supplier->rfc ?? '') }}" maxlength="13" style="text-transform:uppercase" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Tipo de persona</label>
          <select name="person_type" id="staff_person_type" class="form-select">
            <option value="">Seleccionar...</option>
            @foreach($personTypes as $value => $label)
              <option value="{{ $value }}" @selected($selectedPersonType === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-8">
          <label class="form-label">Regímenes fiscales SAT</label>
          <div class="border rounded p-2" id="staff_tax_regimes_wrapper">
            @foreach(['fisica', 'moral'] as $personType)
              <div class="staff-tax-group {{ $selectedPersonType === $personType ? '' : 'd-none' }}" data-person-type="{{ $personType }}">
                <div class="d-grid gap-2">
                  @foreach(\App\Support\SupplierFiscalCatalog::regimesFor($personType) as $regime)
                    <label class="form-check border rounded px-3 py-2 mb-0">
                      <input class="form-check-input me-2" type="checkbox" name="tax_regimes[]" value="{{ $regime['code'] }}"
                             @checked(in_array($regime['code'], $selectedTaxRegimes, true))>
                      <span>{{ $regime['code'] }} - {{ $regime['label'] }}</span>
                    </label>
                  @endforeach
                </div>
              </div>
            @endforeach
            <div id="staff_foreign_tax_note" class="text-muted small {{ $selectedPersonType === 'extranjero' ? '' : 'd-none' }}">
              Los proveedores extranjeros no capturan regímenes fiscales SAT.
            </div>
          </div>
        </div>

        <div class="col-md-8">
          <label class="form-label">Domicilio</label>
          <input type="text" name="address" class="form-control"
                 value="{{ old('address', $supplier->address ?? '') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">Teléfono</label>
          <input type="text" name="phone_number" class="form-control"
                 value="{{ old('phone_number', $supplier->phone_number ?? '') }}">
        </div>

        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control"
                 value="{{ old('email', $supplier->email ?? $user->email) }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">Tipo de proveedor</label>
          <input type="text" name="supplier_type" class="form-control"
                 placeholder="Servicios, Materiales, Transporte, etc."
                 value="{{ old('supplier_type', $supplier->supplier_type ?? '') }}">
        </div>

        <div class="col-12">
          <label class="form-label">Actividades económicas</label>
          <div id="staff_activity_list" class="d-grid gap-2">
            @foreach($economicActivities as $activity)
              <div class="d-flex gap-2 staff-activity-row">
                <input type="text" name="economic_activity[]" class="form-control"
                       value="{{ is_string($activity) ? $activity : '' }}"
                       placeholder="Ej. Venta y distribución de productos industriales">
                <button type="button" class="btn btn-outline-danger staff-remove-activity">Quitar</button>
              </div>
            @endforeach
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="staff_add_activity">
            <i class="ti ti-plus"></i> Agregar actividad
          </button>
        </div>

        <div class="col-md-6">
          <label class="form-label">Persona contacto</label>
          <input type="text" name="contact_person" class="form-control"
                 value="{{ old('contact_person', $supplier->contact_person ?? '') }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">Teléfono contacto</label>
          <input type="text" name="contact_phone" class="form-control"
                 value="{{ old('contact_phone', $supplier->contact_phone ?? '') }}">
        </div>
      </div>
    </div>

    <div class="border rounded p-3">
      <h6 class="text-uppercase text-muted fw-semibold mb-3">
        <i class="ti ti-clipboard-check me-1"></i> REPSE
      </h6>

      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch"
               id="provides_specialized_services"
               name="provides_specialized_services"
               value="1" @checked(old('provides_specialized_services', $supplier->provides_specialized_services ?? false))>
        <label class="form-check-label" for="provides_specialized_services">
          ¿Proporciona servicios especializados (REPSE)?
        </label>
      </div>

      <div id="repseFields" class="{{ old('provides_specialized_services', $supplier->provides_specialized_services ?? false) ? '' : 'd-none' }}">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Número de registro REPSE</label>
            <input type="text" name="repse_registration_number" class="form-control"
                   value="{{ old('repse_registration_number', $supplier->repse_registration_number ?? '') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Vigencia / Fecha de expiración</label>
            <input type="date" name="repse_expiry_date" class="form-control"
                   value="{{ old('repse_expiry_date', optional($supplier->repse_expiry_date ?? null)?->format('Y-m-d')) }}">
          </div>
          <div class="col-12">
            <label class="form-label">Tipos de servicios especializados</label>
            <input type="text" name="specialized_services_types[]" class="form-control"
                   value="{{ old('specialized_services_types.0', isset($supplier) && is_array($supplier->specialized_services_types ?? null) ? implode(', ', $supplier->specialized_services_types) : '') }}"
                   placeholder="Ej: Limpieza industrial, Mantenimiento eléctrico">
            <div class="form-text">Se guardarán como lista.</div>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" name="status" value="{{ old('status', $supplier->status ?? 'pending_docs') }}">
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
    <button type="submit" class="btn btn-primary">
      <i class="ti ti-device-floppy me-1"></i> Guardar
    </button>
  </div>
</form>

<script>
  (function () {
    const sw = document.getElementById('provides_specialized_services');
    const box = document.getElementById('repseFields');
    if (sw && box) {
      sw.addEventListener('change', () => {
        box.classList.toggle('d-none', !sw.checked);
      });
    }

    const personTypeSelect = document.getElementById('staff_person_type');
    const taxGroups = Array.from(document.querySelectorAll('.staff-tax-group'));
    const foreignNote = document.getElementById('staff_foreign_tax_note');

    function syncTaxRegimes() {
      const selectedType = personTypeSelect?.value || '';
      taxGroups.forEach((group) => {
        const show = group.dataset.personType === selectedType;
        group.classList.toggle('d-none', !show);
        if (!show) {
          group.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.checked = false;
          });
        }
      });
      if (foreignNote) {
        foreignNote.classList.toggle('d-none', selectedType !== 'extranjero');
      }
    }

    personTypeSelect?.addEventListener('change', syncTaxRegimes);
    syncTaxRegimes();

    const activityList = document.getElementById('staff_activity_list');
    const addActivityBtn = document.getElementById('staff_add_activity');

    function refreshActivityButtons() {
      const rows = activityList ? Array.from(activityList.querySelectorAll('.staff-activity-row')) : [];
      rows.forEach((row) => {
        const button = row.querySelector('.staff-remove-activity');
        if (button) button.disabled = rows.length === 1;
      });
    }

    function buildActivityRow(value = '') {
      const row = document.createElement('div');
      row.className = 'd-flex gap-2 staff-activity-row';
      row.innerHTML = `
        <input type="text" name="economic_activity[]" class="form-control" value="${value}" placeholder="Ej. Venta y distribución de productos industriales">
        <button type="button" class="btn btn-outline-danger staff-remove-activity">Quitar</button>
      `;
      return row;
    }

    addActivityBtn?.addEventListener('click', () => {
      if (!activityList) return;
      activityList.appendChild(buildActivityRow(''));
      refreshActivityButtons();
    });

    activityList?.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement) || !target.classList.contains('staff-remove-activity')) return;
      const rows = activityList.querySelectorAll('.staff-activity-row');
      if (rows.length === 1) {
        const input = rows[0].querySelector('input');
        if (input) input.value = '';
        return;
      }
      target.closest('.staff-activity-row')?.remove();
      refreshActivityButtons();
    });

    refreshActivityButtons();

    const form = document.getElementById('supplierForm');
    const errBox = document.getElementById('formErrors');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      errBox.classList.add('d-none');
      errBox.innerHTML = '';

      const fd = new FormData(form);
      const specializedServicesRaw = fd.get('specialized_services_types[]') || '';
      const specializedServices = String(specializedServicesRaw)
        .split(',')
        .map(value => value.trim())
        .filter(Boolean);
      fd.delete('specialized_services_types[]');
      specializedServices.forEach((service) => fd.append('specialized_services_types[]', service));

      try {
        const res = await fetch(form.action, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
          },
          body: fd
        });

        if (!res.ok) {
          const data = await res.json().catch(() => ({}));
          if (data?.errors) {
            errBox.classList.remove('d-none');
            errBox.innerHTML = '<strong>Corrige los siguientes campos:</strong><ul class="mb-0">' +
              Object.entries(data.errors).map(([, messages]) => `<li>${messages.join('<br>')}</li>`).join('') +
              '</ul>';
          } else {
            errBox.classList.remove('d-none');
            errBox.textContent = 'Ocurrió un error al guardar.';
          }
          return;
        }

        const modalEl = form.closest('.modal');
        if (modalEl) {
          const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
          modal.hide();
        }
        document.dispatchEvent(new CustomEvent('supplier:updated', { detail: { user_id: {{ $user->id }} } }));
      } catch (err) {
        errBox.classList.remove('d-none');
        errBox.textContent = 'Error de red o servidor. Intenta de nuevo.';
      }
    });
  })();
</script>
