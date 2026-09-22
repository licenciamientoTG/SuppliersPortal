<form id="companyForm" method="POST" action="{{ $company->exists ? route('companies.update', $company) : route('companies.store') }}">
    @csrf
    @if($company->exists)
        @method('PUT')
    @endif

    <div class="modal-header">
        <h5 class="modal-title">{{ $company->exists ? 'Editar Empresa' : 'Nueva Empresa' }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
    </div>

    <div class="modal-body">
        <div id="formErrors" class="d-none"></div>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Código</label>
                <input type="text" name="code" class="form-control" value="{{ old('code', $company->code) }}" required>
            </div>

            <div class="col-md-8">
                <label class="form-label">Nombre Comercial</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $company->name) }}" required>
            </div>

            <div class="col-md-12">
                <label class="form-label">Razón Social</label>
                <input type="text" name="legal_name" class="form-control" value="{{ old('legal_name', $company->legal_name) }}">
            </div>

            <div class="col-md-4">
                <label class="form-label">RFC</label>
                <input type="text" name="rfc" class="form-control" maxlength="13" value="{{ old('rfc', $company->rfc) }}">
            </div>

            <div class="col-md-4">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $company->email) }}">
            </div>

            <div class="col-md-4">
                <label class="form-label">Teléfono</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone', $company->phone) }}">
            </div>

            <div class="col-12">
                <h6 class="text-primary border-bottom pb-1 mb-0 mt-2">
                    <i class="ti ti-file-certificate me-1"></i>Datos fiscales
                    <small class="text-muted fw-normal ms-1">Aparecen en el membrete de las órdenes de compra</small>
                </h6>
            </div>

            <div class="col-md-12">
                <label class="form-label">Régimen Fiscal</label>
                <select name="tax_regime" class="form-select">
                    <option value="">— Sin especificar —</option>
                    @foreach(($taxRegimes ?? \App\Models\Company::taxRegimeOptions()) as $code => $label)
                        <option value="{{ $code }}" @selected((string) old('tax_regime', $company->tax_regime) === (string) $code)>{{ $code }} · {{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Calle</label>
                <input type="text" name="fiscal_street" class="form-control" maxlength="150" placeholder="Av. Insurgentes Sur" value="{{ old('fiscal_street', $company->fiscal_street) }}">
            </div>

            <div class="col-md-3">
                <label class="form-label">No. Exterior</label>
                <input type="text" name="fiscal_exterior_number" class="form-control" maxlength="20" placeholder="1234" value="{{ old('fiscal_exterior_number', $company->fiscal_exterior_number) }}">
            </div>

            <div class="col-md-3">
                <label class="form-label">No. Interior <small class="text-muted">(opcional)</small></label>
                <input type="text" name="fiscal_interior_number" class="form-control" maxlength="20" placeholder="5" value="{{ old('fiscal_interior_number', $company->fiscal_interior_number) }}">
            </div>

            <div class="col-md-6">
                <label class="form-label">Colonia</label>
                <input type="text" name="fiscal_neighborhood" class="form-control" maxlength="100" placeholder="Del Valle" value="{{ old('fiscal_neighborhood', $company->fiscal_neighborhood) }}">
            </div>

            <div class="col-md-6">
                <label class="form-label">Municipio / Alcaldía</label>
                <input type="text" name="fiscal_municipality" class="form-control" maxlength="100" placeholder="Benito Juárez" value="{{ old('fiscal_municipality', $company->fiscal_municipality) }}">
            </div>

            <div class="col-md-6">
                <label class="form-label">Estado</label>
                <input type="text" name="fiscal_state" class="form-control" maxlength="50" placeholder="Ciudad de México" value="{{ old('fiscal_state', $company->fiscal_state) }}">
            </div>

            <div class="col-md-6">
                <label class="form-label">Código Postal</label>
                <input type="text" name="fiscal_postal_code" class="form-control" maxlength="5" inputmode="numeric" pattern="\d{5}" title="5 dígitos" placeholder="03100" value="{{ old('fiscal_postal_code', $company->fiscal_postal_code) }}">
            </div>

            <div class="col-12">
                <small class="text-muted">
                    <i class="ti ti-info-circle me-1"></i>El domicilio es opcional, pero si capturas algún dato debes completar calle, número exterior, colonia, municipio, estado y código postal.
                </small>
                <h6 class="text-primary border-bottom pb-1 mb-0 mt-3">
                    <i class="ti ti-settings me-1"></i>Configuración
                </h6>
            </div>

            <div class="col-md-4">
                <label class="form-label">Dominio</label>
                <input type="text" name="domain" class="form-control" value="{{ old('domain', $company->domain) }}">
            </div>

            <div class="col-md-4">
                <label class="form-label">Sitio Web</label>
                <input type="text" name="website" class="form-control" value="{{ old('website', $company->website) }}">
            </div>

            <div class="col-md-4">
                <label class="form-label">Moneda</label>
                <input type="text" name="currency_code" class="form-control" value="{{ old('currency_code', $company->currency_code ?? 'MXN') }}">
            </div>

            <div class="col-md-6">
                <label class="form-label">Zona Horaria</label>
                <input type="text" name="timezone" class="form-control" value="{{ old('timezone', $company->timezone ?? 'America/Mexico_City') }}">
            </div>

            <div class="col-md-6">
                <label class="form-label">Locale</label>
                <input type="text" name="locale" class="form-control" value="{{ old('locale', $company->locale ?? 'es_MX') }}">
            </div>

            <div class="col-12">
                {{-- Fallback para cuando el checkbox está apagado --}}
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $company->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Activo</label>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">
            <i class="ti ti-device-floppy me-1"></i> Guardar
        </button>
    </div>
</form>
