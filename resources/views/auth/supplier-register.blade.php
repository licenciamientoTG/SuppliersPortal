@php
    use App\Enum\PaymentTerm;

    $viewErrors = $errors ?? new \Illuminate\Support\ViewErrorBag();
    $isForeign = filter_var(old('is_foreign', false), FILTER_VALIDATE_BOOLEAN);
    $oldActivities = old('economic_activity', ['']);
    $oldActivities = is_array($oldActivities) && $oldActivities !== [] ? $oldActivities : [''];
    $selectedServices = old('specialized_services_types', []);
    $selectedCurrencies = old('accepted_currencies', []);
    $selectedCurrencies = is_array($selectedCurrencies) ? $selectedCurrencies : [];
    $repseEnabled = filter_var(old('provides_specialized_services', false), FILTER_VALIDATE_BOOLEAN);
    $parsedToken = old('csf_upload_token');
    $parsedRegimes = old('parsed_tax_regimes_display', '');
    $personTypeValue = old('parsed_person_type', '');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Portal de Proveedores') }} - Registro de proveedor</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #0d2b5e 0%, #123d80 45%, #0f2c5b 100%);
            padding: 24px;
            color: #17212f;
            overflow-x: hidden;
        }
        .page-shell {
            max-width: 980px;
            margin: 0 auto;
            min-height: calc(100vh - 48px);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .form-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 18px;
            box-shadow: 0 18px 48px rgba(7, 21, 45, 0.28);
            overflow: hidden;
            width: 100%;
        }
        .form-header {
            padding: 22px 26px 18px;
            border-bottom: 1px solid #e5edf6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .form-brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .form-brand img {
            width: 138px;
            height: auto;
            flex-shrink: 0;
        }
        .form-header h1 {
            margin: 0;
            font-size: 1.38rem;
            color: #143a72;
        }
        .form-header p {
            margin: 6px 0 0;
            color: #61748a;
            font-size: 0.92rem;
        }
        .form-body {
            padding: 26px;
            display: grid;
            gap: 22px;
        }
        .section {
            border: 1px solid #e5edf6;
            border-radius: 16px;
            padding: 20px;
            background: #fff;
        }
        .section-title {
            margin: 0 0 14px;
            font-size: 1rem;
            font-weight: 700;
            color: #173f75;
        }
        .section-copy {
            margin: -4px 0 14px;
            font-size: 0.9rem;
            color: #607286;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .grid .full { grid-column: 1 / -1; }
        .field { display: grid; gap: 6px; }
        .field label {
            font-size: 0.86rem;
            font-weight: 600;
            color: #27425f;
        }
        .field label.required::after {
            content: ' *';
            color: #c53030;
        }
        .field input,
        .field textarea,
        .field select {
            width: 100%;
            border-radius: 12px;
            border: 1px solid #cfdcea;
            padding: 11px 13px;
            font-size: 0.94rem;
            color: #17212f;
            background: #fff;
        }
        .field textarea { min-height: 92px; resize: vertical; }
        .field input[readonly],
        .field textarea[readonly] {
            background: #f5f8fc;
            color: #34506e;
        }
        .field input.is-editable,
        .field textarea.is-editable {
            background: #fffdf2;
            border-color: #e8d69a;
        }
        .hint {
            font-size: 0.8rem;
            color: #6c7f92;
        }
        .error {
            font-size: 0.82rem;
            color: #c53030;
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 10px;
            padding: 8px 10px;
        }
        .toggle-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #f4f8fc;
        }
        .toggle-row input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        .upload-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
        }
        .upload-row .field {
            flex: 1 1 300px;
        }
        .upload-field {
            display: grid;
            gap: 10px;
        }
        .upload-control {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 56px;
            border-radius: 14px;
            border: 1px solid #cfdcea;
            background: #fff;
            padding: 8px 10px;
        }
        .upload-control.has-file {
            border-color: #b8d0ec;
            background: #f8fbff;
        }
        .upload-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 138px;
            padding: 11px 16px;
            border-radius: 10px;
            border: 1px solid #d0dceb;
            background: #eef4fb;
            color: #143a72;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease, transform .15s ease;
        }
        .upload-trigger:hover {
            background: #e5eef9;
            border-color: #bdd0e8;
            transform: translateY(-1px);
        }
        .upload-name {
            min-width: 0;
            flex: 1;
            color: #4b6078;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .upload-name.is-empty {
            color: #7f93a7;
        }
        .btn {
            border: none;
            border-radius: 12px;
            padding: 12px 18px;
            font-size: 0.92rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 18px rgba(12, 37, 74, 0.12);
        }
        .btn-primary {
            background: #143a72;
            color: #fff;
        }
        .btn-secondary {
            background: #ecf2f9;
            color: #173f75;
        }
        .btn-ghost {
            background: transparent;
            color: #173f75;
            border: 1px solid #d0dceb;
        }
        .banner {
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 0.88rem;
        }
        .banner.info {
            background: #eef6ff;
            border: 1px solid #c9e1ff;
            color: #1d4f8c;
        }
        .banner.success {
            background: #eefbf3;
            border: 1px solid #c9ecd5;
            color: #266241;
        }
        .banner.error {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            color: #c53030;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 6px 11px;
            font-size: 0.76rem;
            font-weight: 700;
            background: #edf4ff;
            color: #1f4e8a;
        }
        .regime-box {
            min-height: 92px;
            white-space: pre-line;
        }
        .activity-list {
            display: grid;
            gap: 10px;
        }
        .activity-item {
            display: flex;
            gap: 10px;
        }
        .activity-item input { flex: 1; }
        .check-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .check-option {
            display: flex;
            gap: 10px;
            align-items: center;
            border: 1px solid #d7e2ef;
            border-radius: 12px;
            padding: 10px 12px;
            background: #fbfdff;
        }
        .radio-line {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }
        .radio-line label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        .footer-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 0 26px 26px;
        }
        .footer-bar a {
            color: #143a72;
            font-weight: 600;
            text-decoration: none;
        }
        .hidden { display: none !important; }
        @media (max-width: 960px) {
            .page-shell {
                align-items: start;
            }
        }
        @media (max-width: 640px) {
            body { padding: 14px; }
            .form-header, .form-body, .footer-bar { padding-left: 18px; padding-right: 18px; }
            .grid, .check-grid { grid-template-columns: 1fr; }
            .footer-bar { flex-direction: column-reverse; align-items: stretch; }
            .footer-bar .btn { width: 100%; }
            .form-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .form-brand {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <main class="form-card">
            <div class="form-header">
                <div class="form-brand">
                    <img src="{{ asset('images/logos/logo_TotalGas_ver.png') }}" alt="TotalGas">
                    <div>
                        <h1>Registro de proveedor</h1>
                        <p>Completa tu alta y continua con la carga documental del portal.</p>
                    </div>
                </div>
                <span class="pill">Formulario unico</span>
            </div>

            <form method="POST" action="{{ route('register') }}" id="supplier-form" novalidate>
                @csrf
                <input type="hidden" name="csf_upload_token" id="csf_upload_token" value="{{ $parsedToken }}">
                <input type="hidden" name="parsed_person_type" id="parsed_person_type" value="{{ $personTypeValue }}">
                <input type="hidden" name="parsed_tax_regimes_display" id="parsed_tax_regimes_display" value="{{ $parsedRegimes }}">

                <div class="form-body">
                    <section class="section">
                        <h2 class="section-title">1. Tipo de registro</h2>
                        <div class="toggle-row">
                            <input type="hidden" name="is_foreign" value="0">
                            <input type="checkbox" id="is_foreign" name="is_foreign" value="1" @checked($isForeign)>
                            <label for="is_foreign" style="margin:0;">
                                Soy proveedor extranjero y no cuento con constancia fiscal SAT para este registro
                            </label>
                        </div>
                    </section>

                    <section class="section" id="csf-section">
                        <h2 class="section-title">2. Constancia de situacion fiscal</h2>
                        <p class="section-copy">
                            Sube un PDF o hasta cinco fotografias para leer los QR y recuperar la informacion fiscal oficial.
                        </p>
                        <div class="upload-row">
                            <div class="field upload-field">
                                <label for="csf_file" class="required">Documento a cargar</label>
                                <div class="upload-control" id="csf-upload-control">
                                    <input type="file" id="csf_file" accept="application/pdf,image/jpeg,image/png" multiple class="hidden">
                                    <button type="button" class="upload-trigger" id="csf-upload-trigger">Seleccionar archivo(s)</button>
                                    <span class="upload-name is-empty" id="csf-file-name">Ningun archivo seleccionado</span>
                                </div>
                                <span class="hint">Un PDF o hasta cinco fotos JPG/PNG. Se consolidan en un solo PDF. Maximo: 10 MB.</span>
                            </div>
                            <button type="button" class="btn btn-secondary" id="parse-csf-button">Analizar constancia</button>
                        </div>
                        <div id="csf-feedback" class="banner info hidden" style="margin-top:14px;"></div>
                        <div class="banner info" style="margin-top:14px;display:flex;align-items:center;gap:14px;">
                            <img src="{{ asset('images/document-guides/csf-pages-and-qr.png') }}" alt="Ejemplo ilustrado de una constancia con todas las páginas y dos códigos QR" style="width:92px;height:92px;object-fit:contain;flex:0 0 auto;">
                            <span>Incluye todas las páginas completas. Los dos códigos QR deben verse completos, nítidos y legibles, tanto en fotos como en PDF.</span>
                        </div>
                        @if ($viewErrors->has('csf_upload_token'))
                            <div class="error" style="margin-top:14px;">{{ $viewErrors->first('csf_upload_token') }}</div>
                        @endif
                    </section>

                    <section class="section">
                        <h2 class="section-title">3. Datos fiscales</h2>
                        <p class="section-copy">
                            Los datos fiscales de proveedores nacionales se autollenan desde SAT. En proveedores extranjeros
                            se capturan manualmente.
                        </p>
                        <div class="grid">
                            <div class="field" id="person-type-wrapper">
                                <label>Tipo de persona</label>
                                <input type="text" id="person_type_display" value="{{ old('parsed_person_type') }}" readonly>
                                <span class="hint">Se determina automaticamente por longitud del RFC cuando la fuente es SAT.</span>
                            </div>

                            <div class="field">
                                <label for="rfc" class="required">RFC</label>
                                <input type="text" id="rfc" name="rfc" value="{{ old('rfc') }}" autocomplete="off">
                                @if ($viewErrors->has('rfc'))<div class="error">{{ $viewErrors->first('rfc') }}</div>@endif
                            </div>

                            <div class="field" id="first-name-wrapper">
                                <label for="first_name" id="first-name-label" class="required">Nombre(s)</label>
                                <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name">
                                <span class="hint" id="first-name-hint">Se obtiene desde la constancia fiscal cuando aplica.</span>
                                @if ($viewErrors->has('first_name'))<div class="error">{{ $viewErrors->first('first_name') }}</div>@endif
                            </div>

                            <div class="field" id="last-name-wrapper">
                                <label for="last_name" id="last-name-label" class="required">Apellidos</label>
                                <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name">
                                <span class="hint" id="last-name-hint">Se obtiene desde la constancia fiscal cuando aplica.</span>
                                @if ($viewErrors->has('last_name'))<div class="error">{{ $viewErrors->first('last_name') }}</div>@endif
                            </div>

                            <div class="field full">
                                <label for="company_name" class="required">Razon social / nombre comercial</label>
                                <input type="text" id="company_name" name="company_name" value="{{ old('company_name') }}">
                                <span class="hint">En persona fisica, se construye con nombre y apellidos.</span>
                                @if ($viewErrors->has('company_name'))<div class="error">{{ $viewErrors->first('company_name') }}</div>@endif
                            </div>

                            <div class="field full">
                                <label for="address" class="required">Domicilio fiscal</label>
                                <textarea id="address" name="address">{{ old('address') }}</textarea>
                                @if ($viewErrors->has('address'))<div class="error">{{ $viewErrors->first('address') }}</div>@endif
                            </div>

                            <div class="field">
                                <label for="postal_code" class="required">Codigo postal</label>
                                <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code') }}" maxlength="5">
                                @if ($viewErrors->has('postal_code'))<div class="error">{{ $viewErrors->first('postal_code') }}</div>@endif
                            </div>

                            <div class="field full">
                                <label for="tax_regimes_display">Regimenes fiscales SAT</label>
                                <textarea id="tax_regimes_display" class="regime-box" readonly>{{ $parsedRegimes }}</textarea>
                                <span class="hint">Solo informativo. Se toma la version obtenida desde SAT.</span>
                            </div>
                        </div>
                    </section>

                    <section class="section">
                        <h2 class="section-title">4. Cuenta y contacto</h2>
                        <div class="grid">
                            <div class="field full">
                                <label for="email" class="required">Correo electronico</label>
                                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username">
                                <span class="hint">Este correo se usara para ingresar al portal.</span>
                                @if ($viewErrors->has('email'))<div class="error">{{ $viewErrors->first('email') }}</div>@endif
                            </div>

                            <div class="field">
                                <label for="password" class="required">Contrasena</label>
                                <input type="password" id="password" name="password" autocomplete="new-password">
                                @if ($viewErrors->has('password'))<div class="error">{{ $viewErrors->first('password') }}</div>@endif
                            </div>

                            <div class="field">
                                <label for="password_confirmation" class="required">Confirmar contrasena</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
                            </div>

                            <div class="field">
                                <label for="supplier_type" class="required">Tipo de proveedor</label>
                                <select id="supplier_type" name="supplier_type">
                                    <option value="">Seleccionar...</option>
                                    <option value="product" @selected(old('supplier_type') === 'product')>Productos</option>
                                    <option value="service" @selected(old('supplier_type') === 'service')>Servicios</option>
                                    <option value="product_service" @selected(old('supplier_type') === 'product_service')>Productos y servicios</option>
                                </select>
                                @if ($viewErrors->has('supplier_type'))<div class="error">{{ $viewErrors->first('supplier_type') }}</div>@endif
                            </div>

                            <div class="field">
                                <label for="phone_number" class="required">Telefono de la empresa</label>
                                <input type="text" id="phone_number" name="phone_number" value="{{ old('phone_number') }}" maxlength="10">
                                @if ($viewErrors->has('phone_number'))<div class="error">{{ $viewErrors->first('phone_number') }}</div>@endif
                            </div>

                            <div class="field">
                                <label for="contact_person" class="required">Persona de contacto</label>
                                <input type="text" id="contact_person" name="contact_person" value="{{ old('contact_person') }}">
                                @if ($viewErrors->has('contact_person'))<div class="error">{{ $viewErrors->first('contact_person') }}</div>@endif
                            </div>

                            <div class="field">
                                <label for="contact_phone">Telefono de contacto</label>
                                <input type="text" id="contact_phone" name="contact_phone" value="{{ old('contact_phone') }}" maxlength="10">
                                @if ($viewErrors->has('contact_phone'))<div class="error">{{ $viewErrors->first('contact_phone') }}</div>@endif
                            </div>

                            <div class="field full">
                                <label for="default_payment_terms" class="required">Condiciones de pago</label>
                                <select id="default_payment_terms" name="default_payment_terms">
                                    <option value="">Seleccionar...</option>
                                    @foreach (PaymentTerm::options() as $value => $label)
                                        <option value="{{ $value }}" @selected(old('default_payment_terms') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if ($viewErrors->has('default_payment_terms'))<div class="error">{{ $viewErrors->first('default_payment_terms') }}</div>@endif
                            </div>

                            <div class="field full">
                                <label class="required">¿En qué moneda(s) operas y/o cotizas?</label>
                                <div class="check-grid">
                                    @foreach (['MXN' => 'Pesos (MXN)', 'USD' => 'Dolares (USD)'] as $value => $label)
                                        <label class="check-option">
                                            <input type="checkbox" name="accepted_currencies[]" value="{{ $value }}" @checked(in_array($value, $selectedCurrencies, true))>
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <span class="hint">Selecciona al menos una. Marca ambas si cotizas en pesos y dolares.</span>
                                @if ($viewErrors->has('accepted_currencies') || $viewErrors->has('accepted_currencies.*'))
                                    <div class="error">{{ $viewErrors->first('accepted_currencies') ?: $viewErrors->first('accepted_currencies.*') }}</div>
                                @endif
                            </div>
                        </div>
                    </section>

                    <section class="section">
                        <h2 class="section-title">5. Actividades economicas</h2>
                        <p class="section-copy">Estas actividades se siguen capturando manualmente.</p>
                        <div class="activity-list" id="activity-list">
                            @foreach ($oldActivities as $index => $activity)
                                <div class="activity-item">
                                    <input type="text" name="economic_activity[]" value="{{ $activity }}" placeholder="Actividad economica {{ $index + 1 }}">
                                    <button type="button" class="btn btn-ghost remove-activity {{ $loop->first ? 'hidden' : '' }}">Quitar</button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-ghost" id="add-activity" style="margin-top:12px;">Agregar actividad</button>
                        @if ($viewErrors->has('economic_activity') || $viewErrors->has('economic_activity.*'))
                            <div class="error" style="margin-top:12px;">
                                {{ $viewErrors->first('economic_activity') ?: $viewErrors->first('economic_activity.*') }}
                            </div>
                        @endif
                    </section>

                    <section class="section">
                        <h2 class="section-title">6. Servicios y REPSE</h2>
                        <div class="grid">
                            <div class="field full">
                                <label class="required">Prestas servicios especializados?</label>
                                <div class="radio-line">
                                    <label><input type="radio" name="provides_specialized_services" value="1" @checked($repseEnabled)> Si</label>
                                    <label><input type="radio" name="provides_specialized_services" value="0" @checked(! $repseEnabled)> No</label>
                                </div>
                                @if ($viewErrors->has('provides_specialized_services'))<div class="error">{{ $viewErrors->first('provides_specialized_services') }}</div>@endif
                            </div>

                            <div id="repse-fields" class="full {{ $repseEnabled ? '' : 'hidden' }}">
                                <div class="grid">
                                    <div class="field">
                                        <label for="repse_registration_number" class="required">Numero REPSE</label>
                                        <input type="text" id="repse_registration_number" name="repse_registration_number" value="{{ old('repse_registration_number') }}">
                                        @if ($viewErrors->has('repse_registration_number'))<div class="error">{{ $viewErrors->first('repse_registration_number') }}</div>@endif
                                    </div>
                                    <div class="field">
                                        <label for="repse_expiry_date" class="required">Vigencia REPSE</label>
                                        <input type="date" id="repse_expiry_date" name="repse_expiry_date" value="{{ old('repse_expiry_date') }}">
                                        @if ($viewErrors->has('repse_expiry_date'))<div class="error">{{ $viewErrors->first('repse_expiry_date') }}</div>@endif
                                    </div>
                                    <div class="field full">
                                        <label class="required">Tipos de servicio especializado</label>
                                        <div class="check-grid">
                                            @foreach (['limpieza' => 'Limpieza', 'vigilancia' => 'Vigilancia', 'mantenimiento' => 'Mantenimiento', 'alimentacion' => 'Alimentacion', 'contabilidad' => 'Contabilidad', 'sistemas' => 'Sistemas', 'otros' => 'Otros'] as $value => $label)
                                                <label class="check-option">
                                                    <input type="checkbox" name="specialized_services_types[]" value="{{ $value }}" @checked(in_array($value, $selectedServices, true))>
                                                    <span>{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @if ($viewErrors->has('specialized_services_types') || $viewErrors->has('specialized_services_types.*'))
                                            <div class="error">{{ $viewErrors->first('specialized_services_types') ?: $viewErrors->first('specialized_services_types.*') }}</div>
                                        @endif
                                    </div>
                                    <div class="field full {{ in_array('otros', $selectedServices, true) ? '' : 'hidden' }}" id="otros-wrapper">
                                        <label for="otros_descripcion" class="required">Describe otros servicios</label>
                                        <input type="text" id="otros_descripcion" name="otros_descripcion" value="{{ old('otros_descripcion') }}">
                                        @if ($viewErrors->has('otros_descripcion'))<div class="error">{{ $viewErrors->first('otros_descripcion') }}</div>@endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="section">
                        <h2 class="section-title">7. Confirmacion</h2>
                        <div class="banner info" id="confirmation-copy">
                            Confirma que la informacion mostrada y/o capturada corresponde a tus datos reales y autorizas
                            su uso para el proceso de alta como proveedor.
                        </div>
                        <div class="toggle-row" style="margin-top:14px;">
                            <input type="checkbox" id="accepted_prefilled_data" name="accepted_prefilled_data" value="1" @checked(old('accepted_prefilled_data'))>
                            <label for="accepted_prefilled_data" style="margin:0;">
                                Confirmo que estos datos son correctos y asumo responsabilidad sobre su veracidad.
                            </label>
                        </div>
                        @if ($viewErrors->has('accepted_prefilled_data'))
                            <div class="error" style="margin-top:12px;">{{ $viewErrors->first('accepted_prefilled_data') }}</div>
                        @endif
                    </section>
                </div>

                <div class="footer-bar">
                    <a href="{{ route('login') }}">Ya tengo cuenta</a>
                    <button type="submit" class="btn btn-primary">Crear cuenta y continuar</button>
                </div>
            </form>
        </main>
    </div>

    <template id="activity-template">
        <div class="activity-item">
            <input type="text" name="economic_activity[]" placeholder="Actividad economica">
            <button type="button" class="btn btn-ghost remove-activity">Quitar</button>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const foreignToggle = document.getElementById('is_foreign');
            const csfSection = document.getElementById('csf-section');
            const parseButton = document.getElementById('parse-csf-button');
            const csfFeedback = document.getElementById('csf-feedback');
            const csfFileInput = document.getElementById('csf_file');
            const csfUploadTrigger = document.getElementById('csf-upload-trigger');
            const csfUploadControl = document.getElementById('csf-upload-control');
            const csfFileName = document.getElementById('csf-file-name');
            const csfTokenInput = document.getElementById('csf_upload_token');
            const personTypeInput = document.getElementById('parsed_person_type');
            const personTypeDisplay = document.getElementById('person_type_display');
            const taxRegimesDisplay = document.getElementById('tax_regimes_display');
            const parsedRegimesInput = document.getElementById('parsed_tax_regimes_display');
            const firstNameInput = document.getElementById('first_name');
            const lastNameInput = document.getElementById('last_name');
            const firstNameWrapper = document.getElementById('first-name-wrapper');
            const lastNameWrapper = document.getElementById('last-name-wrapper');
            const companyNameInput = document.getElementById('company_name');
            const rfcInput = document.getElementById('rfc');
            const addressInput = document.getElementById('address');
            const postalCodeInput = document.getElementById('postal_code');
            const emailInput = document.getElementById('email');
            const contactPersonInput = document.getElementById('contact_person');
            const acceptedCheckbox = document.getElementById('accepted_prefilled_data');
            const firstNameHint = document.getElementById('first-name-hint');
            const lastNameHint = document.getElementById('last-name-hint');
            const activityList = document.getElementById('activity-list');
            const activityTemplate = document.getElementById('activity-template');
            const addActivityButton = document.getElementById('add-activity');
            const repseFields = document.getElementById('repse-fields');
            const repseRadios = document.querySelectorAll('input[name="provides_specialized_services"]');
            const otrosWrapper = document.getElementById('otros-wrapper');
            const fiscalInputs = [firstNameInput, lastNameInput, companyNameInput, rfcInput, addressInput, postalCodeInput];

            function setFeedback(type, message) {
                csfFeedback.className = 'banner ' + type;
                csfFeedback.textContent = message;
                csfFeedback.classList.remove('hidden');
            }

            function clearFeedback() {
                csfFeedback.className = 'banner info hidden';
                csfFeedback.textContent = '';
            }

            function setInputEditableState(input, editable) {
                input.readOnly = !editable;
                input.classList.toggle('is-editable', editable);
            }

            function applyReadonlyStateForNationalFlow() {
                const isMoral = personTypeInput.value === 'moral';
                const isPhysical = personTypeInput.value === 'fisica';
                const namesMissing = isPhysical && (!firstNameInput.value.trim() || !lastNameInput.value.trim());

                firstNameWrapper.classList.toggle('hidden', isMoral);
                lastNameWrapper.classList.toggle('hidden', isMoral);
                firstNameInput.disabled = isMoral;
                lastNameInput.disabled = isMoral;

                setInputEditableState(firstNameInput, namesMissing);
                setInputEditableState(lastNameInput, namesMissing);
                setInputEditableState(companyNameInput, false);
                setInputEditableState(rfcInput, false);
                setInputEditableState(addressInput, false);
                setInputEditableState(postalCodeInput, false);

                if (namesMissing) {
                    firstNameHint.textContent = 'La constancia no incluyo este dato. Capturalo manualmente.';
                    lastNameHint.textContent = 'La constancia no incluyo este dato. Capturalo manualmente.';
                } else if (isMoral) {
                    firstNameHint.textContent = '';
                    lastNameHint.textContent = '';
                } else {
                    firstNameHint.textContent = 'Se obtiene desde la constancia fiscal cuando aplica.';
                    lastNameHint.textContent = 'Se obtiene desde la constancia fiscal cuando aplica.';
                }
            }

            function syncSelectedFile() {
                const files = Array.from(csfFileInput.files || []);

                if (!files.length) {
                    csfFileName.textContent = 'Ningun archivo seleccionado';
                    csfFileName.classList.add('is-empty');
                    csfUploadControl.classList.remove('has-file');
                    return;
                }

                csfFileName.textContent = files.length === 1
                    ? files[0].name
                    : `${files.length} fotografias seleccionadas`;
                csfFileName.classList.remove('is-empty');
                csfUploadControl.classList.add('has-file');
            }

            function updateFiscalMode() {
                const isForeign = foreignToggle.checked;
                csfSection.classList.toggle('hidden', isForeign);

                personTypeDisplay.value = isForeign ? 'extranjero' : personTypeInput.value;
                personTypeDisplay.readOnly = true;
                taxRegimesDisplay.value = isForeign ? 'No aplica para proveedores extranjeros.' : parsedRegimesInput.value;
                acceptedCheckbox.checked = false;

                if (isForeign) {
                    firstNameWrapper.classList.remove('hidden');
                    lastNameWrapper.classList.remove('hidden');
                    firstNameInput.disabled = false;
                    lastNameInput.disabled = false;
                    fiscalInputs.forEach(function (input) {
                        setInputEditableState(input, true);
                    });
                    csfTokenInput.value = '';
                    personTypeInput.value = 'extranjero';
                    firstNameHint.textContent = 'Capturalo manualmente para proveedores extranjeros.';
                    lastNameHint.textContent = 'Capturalo manualmente para proveedores extranjeros.';
                    clearFeedback();
                    return;
                }

                applyReadonlyStateForNationalFlow();
            }

            function applyParsedData(data) {
                csfTokenInput.value = data.token;
                personTypeInput.value = data.person_type || '';
                personTypeDisplay.value = data.person_type || '';
                firstNameInput.value = data.first_name || '';
                lastNameInput.value = data.last_name || '';
                companyNameInput.value = data.company_name || '';
                rfcInput.value = data.rfc || '';
                addressInput.value = data.address || '';
                postalCodeInput.value = data.postal_code || '';

                const regimeText = Array.isArray(data.tax_regime_labels) ? data.tax_regime_labels.join('\n') : '';
                taxRegimesDisplay.value = regimeText;
                parsedRegimesInput.value = regimeText;

                if (!emailInput.value && data.sat_email) {
                    emailInput.value = data.sat_email;
                }

                if (!contactPersonInput.value) {
                    contactPersonInput.value = data.company_name || [data.first_name, data.last_name].filter(Boolean).join(' ');
                }

                applyReadonlyStateForNationalFlow();
                acceptedCheckbox.checked = false;
            }

            async function parseCsf() {
                const files = Array.from(csfFileInput.files || []);
                if (!files.length) {
                    setFeedback('error', 'Selecciona primero un PDF o las fotografias de la constancia fiscal.');
                    return;
                }

                const formData = new FormData();
                files.forEach((file) => formData.append('csf[]', file));

                parseButton.disabled = true;
                parseButton.textContent = 'Analizando...';
                clearFeedback();

                try {
                    const response = await fetch('{{ route('supplier.register.parse-csf') }}', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        setFeedback('error', payload.message || 'No fue posible procesar la constancia fiscal.');
                        return;
                    }

                    applyParsedData({
                        token: payload.token,
                        ...payload.data,
                    });
                    setFeedback('success', 'Constancia analizada correctamente. Revisa y confirma tus datos antes de enviar.');
                } catch (error) {
                    setFeedback('error', 'Ocurrio un error al analizar la constancia fiscal. Intenta de nuevo.');
                } finally {
                    parseButton.disabled = false;
                    parseButton.textContent = 'Analizar constancia';
                }
            }

            function bindRemoveActivity(button) {
                button.addEventListener('click', function () {
                    const items = activityList.querySelectorAll('.activity-item');
                    if (items.length <= 1) {
                        const input = items[0].querySelector('input');
                        if (input) {
                            input.value = '';
                        }
                        return;
                    }

                    button.closest('.activity-item').remove();
                    syncActivityButtons();
                });
            }

            function syncActivityButtons() {
                const items = activityList.querySelectorAll('.activity-item');
                items.forEach(function (item, index) {
                    const removeButton = item.querySelector('.remove-activity');
                    if (removeButton) {
                        removeButton.classList.toggle('hidden', items.length === 1 && index === 0);
                    }
                });
            }

            function updateRepseFields() {
                const enabled = Array.from(repseRadios).some(function (radio) {
                    return radio.checked && radio.value === '1';
                });

                repseFields.classList.toggle('hidden', !enabled);
            }

            function updateOtrosField() {
                const checked = document.querySelector('input[name="specialized_services_types[]"][value="otros"]');
                otrosWrapper.classList.toggle('hidden', !checked || !checked.checked);
            }

            parseButton.addEventListener('click', parseCsf);
            csfUploadTrigger.addEventListener('click', function () {
                csfFileInput.click();
            });
            csfFileInput.addEventListener('change', function () {
                syncSelectedFile();
                clearFeedback();
            });
            foreignToggle.addEventListener('change', updateFiscalMode);
            addActivityButton.addEventListener('click', function () {
                const fragment = activityTemplate.content.cloneNode(true);
                const removeButton = fragment.querySelector('.remove-activity');
                bindRemoveActivity(removeButton);
                activityList.appendChild(fragment);
                syncActivityButtons();
            });

            activityList.querySelectorAll('.remove-activity').forEach(bindRemoveActivity);
            repseRadios.forEach(function (radio) {
                radio.addEventListener('change', updateRepseFields);
            });
            document.querySelectorAll('input[name="specialized_services_types[]"]').forEach(function (checkbox) {
                checkbox.addEventListener('change', updateOtrosField);
            });

            updateFiscalMode();
            updateRepseFields();
            updateOtrosField();
            syncSelectedFile();

            if (csfTokenInput.value && !foreignToggle.checked) {
                setFeedback('success', 'Ya cuentas con una constancia fiscal validada en esta sesion. Puedes continuar con el registro.');
                applyReadonlyStateForNationalFlow();
            }

            syncActivityButtons();
        });
    </script>
</body>
</html>
