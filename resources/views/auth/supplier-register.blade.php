{{-- resources/views/auth/supplier-register.blade.php --}}
@php
    $viewErrors = $errors ?? new \Illuminate\Support\ViewErrorBag();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }} - Registro de Proveedor</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { font-family: 'Poppins', sans-serif; box-sizing: border-box; }

        body {
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            background: linear-gradient(135deg, #0d2b5e 0%, #1a4b96 50%, #0d2b5e 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: -120px; right: -120px;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: rgba(169,202,72,.07);
            pointer-events: none;
            z-index: 0;
        }

        body::after {
            content: '';
            position: fixed;
            bottom: -100px; left: -100px;
            width: 350px; height: 350px;
            border-radius: 50%;
            background: rgba(169,202,72,.05);
            pointer-events: none;
            z-index: 0;
        }

        .auth-wrap {
            width: 100%;
            max-width: 780px;
            position: relative;
            z-index: 1;
        }

        /* ===== CARD ===== */
        .reg-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
            overflow: hidden;
        }

        /* ===== TOP-BAR ===== */
        .card-topbar {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 20px;
            border-bottom: 3px solid #A9CA48;
        }

        .topbar-divider {
            width: 1px;
            height: 32px;
            background: #e9ecef;
            flex-shrink: 0;
        }

        .topbar-title-wrap { flex: 1; min-width: 0; }

        .topbar-title {
            display: block;
            font-size: 15px;
            font-weight: 700;
            color: #1a4b96;
            line-height: 1.2;
        }

        .topbar-sub {
            display: block;
            font-size: 11px;
            color: #6c757d;
        }

        /* Step indicator */
        .step-indicator {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .step-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
        }

        .step-dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            transition: background 0.3s, border-color 0.3s, color 0.3s;
        }

        .step-dot.active {
            background: #A9CA48;
            color: #fff;
            border: 2px solid #A9CA48;
        }

        .step-dot.inactive {
            background: #fff;
            color: #adb5bd;
            border: 2px solid #dee2e6;
        }

        .step-label {
            font-size: 11px;
            color: #adb5bd;
            white-space: nowrap;
        }

        .step-label.active {
            color: #A9CA48;
            font-weight: 600;
        }

        .step-line {
            width: 36px;
            height: 3px;
            background: #dee2e6;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 16px;
            transition: background 0.4s;
        }

        .step-line.done { background: #A9CA48; }

        #progress-fill {
            height: 100%;
            background: #A9CA48;
            width: 0%;
            transition: width 0.4s ease;
        }

        /* ===== FORM BODY ===== */
        .card-body-form { padding: 24px 24px 16px; }

        /* ===== STEP SECTIONS ===== */
        .step-section { display: none; }

        .step-section.active {
            display: block;
            animation: fadeInSlide 0.3s ease-out;
        }

        .step-section:not(.hidden) { display: block; }

        .step-section.hidden { display: none !important; }

        @keyframes fadeInSlide {
            from { opacity: 0; transform: translateX(10px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        /* ===== SECTION TITLES ===== */
        .section-title {
            border-left: 3px solid #A9CA48;
            padding-left: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #343a40;
            margin: 0 0 16px;
        }

        /* ===== FORM GRID ===== */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-grid > .full { grid-column: 1 / -1; }

        @media (max-width: 580px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-grid > .full { grid-column: auto; }
        }

        /* ===== FORM GROUP ===== */
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        /* ===== LABELS ===== */
        .form-label {
            font-size: 12px !important;
            font-weight: 500 !important;
            color: #495057 !important;
        }

        /* ===== REQUIRED ASTERISK ===== */
        .required-label::after {
            content: ' *';
            color: #dc2626;
            font-weight: 700;
        }

        /* ===== INPUTS ===== */
        .reg-input {
            width: 100%;
            border: 1.5px solid #dee2e6;
            border-radius: 7px;
            padding: 9px 12px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            color: #343a40;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
            appearance: none;
        }

        .reg-input:focus {
            border-color: #A9CA48;
            box-shadow: 0 0 0 3px rgba(169,202,72,.15);
        }

        select.reg-input {
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23adb5bd" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 30px;
        }

        textarea.reg-input {
            resize: vertical;
            min-height: 80px;
        }

        /* ===== HINTS ===== */
        .input-hint { font-size: 11px; color: #adb5bd; }

        /* ===== ERRORS ===== */
        .input-error, .validation-error {
            font-size: 11px !important;
            color: #dc2626 !important;
            background: #fff5f5;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 4px 8px;
        }

        /* ===== RADIO BUTTONS ===== */
        .radio-group { display: flex; flex-direction: row; gap: 16px; flex-wrap: wrap; }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
            color: #495057;
            user-select: none;
        }

        .radio-option input[type="radio"] { display: none; }

        .radio-custom {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #dee2e6;
            position: relative;
            flex-shrink: 0;
            transition: border-color 0.2s;
        }

        .radio-option input:checked ~ .radio-custom { border-color: #A9CA48; }

        .radio-option input:checked ~ .radio-custom::after {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 10px; height: 10px;
            border-radius: 50%;
            background: #A9CA48;
        }

        /* ===== REPSE CONTAINER ===== */
        .repse-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            background: #f8f9fa;
            border: 1.5px solid #dee2e6;
            border-radius: 8px;
            padding: 16px;
        }

        .repse-container > .full { grid-column: 1 / -1; }

        @media (max-width: 580px) {
            .repse-container { grid-template-columns: 1fr; }
            .repse-container > .full { grid-column: auto; }
        }

        /* ===== MULTISELECT ===== */
        .custom-multiselect { position: relative; width: 100%; }

        .multiselect-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 12px;
            border: 1.5px solid #dee2e6;
            border-radius: 7px;
            background: #fff;
            cursor: pointer;
            font-size: 13px;
            color: #6c757d;
            transition: border-color 0.2s;
        }

        .multiselect-header:hover, .multiselect-header.active { border-color: #A9CA48; }

        .dropdown-arrow { font-size: 11px; color: #adb5bd; transition: transform 0.2s; }
        .multiselect-header.active .dropdown-arrow { transform: rotate(180deg); }

        .multiselect-options {
            position: absolute;
            top: 100%; left: 0; right: 0;
            background: #fff;
            border: 1.5px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 7px 7px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,.08);
        }

        .multiselect-options.show { display: block; }

        .multiselect-option {
            display: flex;
            align-items: center;
            padding: 9px 12px;
            cursor: pointer;
            font-size: 13px;
            color: #495057;
            border-bottom: 1px solid #f3f4f6;
            transition: background 0.15s;
        }

        .multiselect-option:hover { background: #f8f9fa; }
        .multiselect-option:last-child { border-bottom: none; }
        .multiselect-option input[type="checkbox"] { display: none; }

        .checkmark {
            width: 16px; height: 16px;
            border: 1.5px solid #dee2e6;
            border-radius: 3px;
            margin-right: 10px;
            position: relative;
            flex-shrink: 0;
            transition: all 0.15s;
        }

        .multiselect-option input:checked + .checkmark {
            background: #A9CA48;
            border-color: #A9CA48;
        }

        .multiselect-option input:checked + .checkmark::after {
            content: '✓';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
        }

        /* ===== CARD FOOTER ===== */
        .card-footer {
            background: #fafafa;
            border-top: 1px solid #f0f0f0;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-link {
            color: #1a4b96;
            font-size: 12px;
            text-decoration: none;
            font-weight: 500;
        }

        .footer-link:hover { text-decoration: underline; }

        .btn-primary {
            background: #1a4b96;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 24px;
            font-size: 13px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }

        .btn-primary:hover { background: #15407f; }
        .btn-arrow { color: #A9CA48; font-size: 15px; line-height: 1; }

        .btn-secondary {
            background: #fff;
            border: 1.5px solid #dee2e6;
            color: #6c757d;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: border-color 0.2s, color 0.2s;
        }

        .btn-secondary:hover { border-color: #adb5bd; color: #495057; }

        @media (max-width: 480px) {
            .card-topbar { flex-wrap: wrap; }
            .step-indicator { width: 100%; justify-content: center; }
            .card-footer { flex-direction: column-reverse; gap: 10px; }
            .btn-primary, .btn-secondary { width: 100%; justify-content: center; }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-required="1"]').forEach(function (input) {
                var group = input.closest('.form-group');
                if (group) {
                    var label = group.querySelector('label');
                    if (label) label.classList.add('required-label');
                }
            });
        });
    </script>
    {{-- Script de REPSE eliminado: su lógica está consolidada en el IIFE principal al final del body --}}
</head>
<body class="font-sans antialiased">

    <div class="auth-wrap">
        <div class="reg-card">

            <!-- Top-bar -->
            <div class="card-topbar">
                <img src="{{ asset('images/logos/logo_TotalGas_ver.png') }}" height="44" alt="TotalGas">
                <div class="topbar-divider"></div>
                <div class="topbar-title-wrap">
                    <span class="topbar-title">Portal de Proveedores</span>
                    <span class="topbar-sub">TotalGas Energy</span>
                </div>
                <div class="step-indicator">
                    <div class="step-group">
                        <div id="dot-1" class="step-dot active">1</div>
                        <span id="label-1" class="step-label active">Cuenta</span>
                    </div>
                    <div class="step-line" id="connector-1"><div id="progress-fill"></div></div>
                    <div class="step-group">
                        <div id="dot-2" class="step-dot inactive">2</div>
                        <span id="label-2" class="step-label">Empresa</span>
                    </div>
                    <div class="step-line" id="connector-2"></div>
                    <div class="step-group">
                        <div id="dot-3" class="step-dot inactive">3</div>
                        <span id="label-3" class="step-label">Servicios</span>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('register') }}" id="supplier-form" novalidate>
                @csrf

                <!-- ===== STEP 1: Datos de la Cuenta ===== -->
                <div data-step="1" class="step-section active">
                    <div class="card-body-form">
                        <div class="section-title">Datos de la Cuenta --</div>
                        <div class="form-grid">

                            <div class="form-group">
                                <x-input-label for="first_name" :value="__('Nombre(s)')" class="form-label" />
                                <x-text-input id="first_name" class="reg-input" type="text" name="first_name"
                                    :value="old('first_name')" data-required="1" autofocus autocomplete="given-name" />
                                <x-input-error :messages="$viewErrors->get('first_name')" class="input-error" />
                            </div>

                            <div class="form-group">
                                <x-input-label for="last_name" :value="__('Apellidos')" class="form-label" />
                                <x-text-input id="last_name" class="reg-input" type="text" name="last_name"
                                    :value="old('last_name')" data-required="1" autocomplete="family-name" />
                                <x-input-error :messages="$viewErrors->get('last_name')" class="input-error" />
                            </div>

                            <div class="form-group full">
                                <x-input-label for="email" :value="__('Correo electrónico')" class="form-label" />
                                <x-text-input id="email" class="reg-input" type="email" name="email"
                                    :value="old('email')" data-required="1" autocomplete="username" />
                                <x-input-error :messages="$viewErrors->get('email')" class="input-error" />
                            </div>

                            <div class="form-group">
                                <x-input-label for="password" :value="__('Contraseña')" class="form-label" />
                                <x-text-input id="password" class="reg-input" type="password" name="password"
                                    data-required="1" autocomplete="new-password" />
                                <x-input-error :messages="$viewErrors->get('password')" class="input-error" />
                                <x-password-requirements input-id="password" confirm-id="password_confirmation" theme="dark" />
                            </div>

                            <div class="form-group">
                                <x-input-label for="password_confirmation" :value="__('Confirmar contraseña')" class="form-label" />
                                <x-text-input id="password_confirmation" class="reg-input" type="password"
                                    name="password_confirmation" data-required="1" autocomplete="new-password" />
                                <x-input-error :messages="$viewErrors->get('password_confirmation')" class="input-error" />
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ===== STEP 2: Datos Fiscales ===== -->
                <div data-step="2" class="step-section hidden">
                    <div class="card-body-form">
                        <div class="section-title">Datos Fiscales de la Empresa</div>
                        <div class="form-grid">

                            <div class="form-group full">
                                <x-input-label for="company_name" :value="__('Razón social / Nombre comercial')" class="form-label" />
                                <x-text-input id="company_name" class="reg-input" type="text" name="company_name"
                                    :value="old('company_name')" data-required="1" />
                                <x-input-error :messages="$viewErrors->get('company_name')" class="input-error" />
                            </div>

                            <div class="form-group">
                                <x-input-label for="rfc" :value="__('RFC')" class="form-label" />
                                <x-text-input id="rfc" class="reg-input" type="text" name="rfc"
                                    :value="old('rfc')" data-required="1" maxlength="13"
                                    style="text-transform: uppercase;" inputmode="latin" autocomplete="off" />
                                <div class="input-hint">3–4 letras + 6 dígitos (YYMMDD) + 3 alfanuméricos</div>
                                <x-input-error :messages="$viewErrors->get('rfc')" class="input-error" />
                            </div>

                            <div class="form-group">
                                <x-input-label for="tax_regime" :value="__('Régimen fiscal')" class="form-label" />
                                <select id="tax_regime" name="tax_regime" class="reg-input" data-required="1">
                                    <option value="" disabled {{ old('tax_regime') ? '' : 'selected' }}>Selecciona una opción</option>
                                    <option value="individual" {{ old('tax_regime') === 'individual' ? 'selected' : '' }}>Persona Física</option>
                                    <option value="corporation" {{ old('tax_regime') === 'corporation' ? 'selected' : '' }}>Persona Moral</option>
                                    <option value="resico" {{ old('tax_regime') === 'resico' ? 'selected' : '' }}>RESICO</option>
                                </select>
                                <x-input-error :messages="$viewErrors->get('tax_regime')" class="input-error" />
                            </div>

                            <div class="form-group full">
                                <x-input-label for="economic_activity" :value="__('Actividad económica')" class="form-label" />
                                <x-text-input id="economic_activity" class="reg-input" type="text" name="economic_activity"
                                    :value="old('economic_activity')" data-required="1" />
                                <div class="input-hint">Tal como aparece en la constancia de situación fiscal</div>
                                <x-input-error :messages="$viewErrors->get('economic_activity')" class="input-error" />
                            </div>

                            <div class="form-group full">
                                <x-input-label for="address" :value="__('Domicilio fiscal')" class="form-label" />
                                <textarea id="address" name="address" class="reg-input" rows="3"
                                    data-required="1"
                                    placeholder="Ingrese la dirección completa del domicilio fiscal">{{ old('address') }}</textarea>
                                <x-input-error :messages="$viewErrors->get('address')" class="input-error" />
                            </div>

                            <div class="form-group">
                                <x-input-label for="postal_code" :value="__('Código postal')" class="form-label" />
                                <x-text-input id="postal_code" class="reg-input" type="text" name="postal_code"
                                    :value="old('postal_code')" data-required="1" inputmode="numeric"
                                    pattern="\d{5}" maxlength="5" minlength="5" autocomplete="postal-code" />
                                <div class="input-hint">5 dígitos</div>
                                <x-input-error :messages="$viewErrors->get('postal_code')" class="input-error" />
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ===== STEP 3: Contacto y Servicios ===== -->
                <div data-step="3" class="step-section hidden">
                    <div class="card-body-form">
                        <div class="section-title">Contacto y Tipo de Servicios</div>
                        <div class="form-grid">

                            <div class="form-group">
                                <x-input-label for="phone_number" :value="__('Teléfono de la empresa')" class="form-label" />
                                <x-text-input id="phone_number" class="reg-input" type="tel" name="phone_number"
                                    :value="old('phone_number')" data-required="1" data-phone="10"
                                    inputmode="numeric" pattern="\d{10}" maxlength="10" minlength="10" autocomplete="tel" />
                                <div class="input-hint">10 dígitos exactos</div>
                                <x-input-error :messages="$viewErrors->get('phone_number')" class="input-error" />
                            </div>

                            <div class="form-group">
                                <x-input-label for="contact_person" :value="__('Persona de contacto')" class="form-label" />
                                <x-text-input id="contact_person" class="reg-input" type="text" name="contact_person"
                                    :value="old('contact_person')" data-required="1" />
                                <x-input-error :messages="$viewErrors->get('contact_person')" class="input-error" />
                            </div>

                            <div class="form-group">
                                <x-input-label for="contact_phone" :value="__('Teléfono de contacto (opcional)')" class="form-label" />
                                <x-text-input id="contact_phone" class="reg-input" type="tel" name="contact_phone"
                                    :value="old('contact_phone')" data-phone="10" inputmode="numeric"
                                    pattern="\d{10}" maxlength="10" minlength="10" autocomplete="tel" />
                                <div class="input-hint">10 dígitos si se proporciona</div>
                                <x-input-error :messages="$viewErrors->get('contact_phone')" class="input-error" />
                            </div>

                            <div class="form-group">
                                <x-input-label for="supplier_type" :value="__('Tipo de proveedor')" class="form-label" />
                                <select id="supplier_type" name="supplier_type" class="reg-input" data-required="1">
                                    <option value="" disabled {{ old('supplier_type') ? '' : 'selected' }}>Selecciona una opción</option>
                                    <option value="product" {{ old('supplier_type') === 'product' ? 'selected' : '' }}>Productos</option>
                                    <option value="service" {{ old('supplier_type') === 'service' ? 'selected' : '' }}>Servicios</option>
                                    <option value="product_service" {{ old('supplier_type') === 'product_service' ? 'selected' : '' }}>Productos y Servicios</option>
                                </select>
                                <x-input-error :messages="$viewErrors->get('supplier_type')" class="input-error" />
                            </div>

                            <div class="form-group full">
                                <x-input-label for="default_payment_terms" :value="__('Condiciones de pago')" class="form-label" />
                                <select id="default_payment_terms" name="default_payment_terms" class="reg-input" data-required="1">
                                    @foreach(\App\Enum\PaymentTerm::options() as $value => $label)
                                        <option value="{{ $value }}" {{ old('default_payment_terms', 'CASH') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="input-hint">Condiciones de pago por defecto para OC y cotizaciones.</div>
                                <x-input-error :messages="$viewErrors->get('default_payment_terms')" class="input-error" />
                            </div>

                            <!-- REPSE Question -->
                            <div class="form-group full">
                                <x-input-label for="provides_specialized_services" :value="__('¿Presta servicios especializados u obras especializadas?')" class="form-label" />
                                <div class="radio-group">
                                    <label class="radio-option">
                                        <input type="radio" name="provides_specialized_services" value="1" id="repse_yes"
                                            {{ old('provides_specialized_services') === '1' ? 'checked' : '' }}>
                                        <span class="radio-custom"></span>
                                        Sí
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="provides_specialized_services" value="0" id="repse_no"
                                            {{ old('provides_specialized_services') === '0' ? 'checked' : '' }}>
                                        <span class="radio-custom"></span>
                                        No
                                    </label>
                                </div>
                                <div class="input-hint">Limpieza, vigilancia, mantenimiento, contabilidad, etc.</div>
                                <x-input-error :messages="$viewErrors->get('provides_specialized_services')" class="input-error" />
                            </div>

                            <!-- REPSE Fields (initially hidden) -->
                            <div id="repse-fields" class="full repse-container" style="display: none;">

                                <div class="form-group">
                                    <x-input-label for="repse_registration_number" :value="__('Número de Registro REPSE')" class="form-label" />
                                    <x-text-input id="repse_registration_number" class="reg-input" type="text"
                                        name="repse_registration_number" :value="old('repse_registration_number')"
                                        placeholder="Ej: REPSE-123456789" data-conditional-required="repse" />
                                    <div class="input-hint">Formato: REPSE seguido del número asignado</div>
                                    <x-input-error :messages="$viewErrors->get('repse_registration_number')" class="input-error" />
                                </div>

                                <div class="form-group">
                                    <x-input-label for="repse_expiry_date" :value="__('Fecha de vencimiento REPSE')" class="form-label" />
                                    <x-text-input id="repse_expiry_date" class="reg-input" type="date"
                                        name="repse_expiry_date" :value="old('repse_expiry_date')"
                                        data-conditional-required="repse" />
                                    <div class="input-hint">El registro debe estar vigente</div>
                                    <x-input-error :messages="$viewErrors->get('repse_expiry_date')" class="input-error" />
                                </div>

                                <div class="form-group full">
                                    <x-input-label for="specialized_services_dropdown" :value="__('Tipos de servicios especializados')" class="form-label" />
                                    <div class="custom-multiselect" id="custom-multiselect">
                                        <div class="multiselect-header" onclick="toggleDropdown()">
                                            <span id="selected-text">Seleccionar servicios...</span>
                                            <span class="dropdown-arrow">▼</span>
                                        </div>
                                        <div class="multiselect-options" id="multiselect-options">
                                            <label class="multiselect-option">
                                                <input type="checkbox" name="specialized_services_types[]" value="limpieza"
                                                    {{ in_array('limpieza', old('specialized_services_types', [])) ? 'checked' : '' }}>
                                                <span class="checkmark"></span>Servicios de limpieza
                                            </label>
                                            <label class="multiselect-option">
                                                <input type="checkbox" name="specialized_services_types[]" value="vigilancia"
                                                    {{ in_array('vigilancia', old('specialized_services_types', [])) ? 'checked' : '' }}>
                                                <span class="checkmark"></span>Vigilancia y seguridad
                                            </label>
                                            <label class="multiselect-option">
                                                <input type="checkbox" name="specialized_services_types[]" value="mantenimiento"
                                                    {{ in_array('mantenimiento', old('specialized_services_types', [])) ? 'checked' : '' }}>
                                                <span class="checkmark"></span>Mantenimiento
                                            </label>
                                            <label class="multiselect-option">
                                                <input type="checkbox" name="specialized_services_types[]" value="alimentacion"
                                                    {{ in_array('alimentacion', old('specialized_services_types', [])) ? 'checked' : '' }}>
                                                <span class="checkmark"></span>Servicios de alimentación
                                            </label>
                                            <label class="multiselect-option">
                                                <input type="checkbox" name="specialized_services_types[]" value="contabilidad"
                                                    {{ in_array('contabilidad', old('specialized_services_types', [])) ? 'checked' : '' }}>
                                                <span class="checkmark"></span>Servicios contables/administrativos
                                            </label>
                                            <label class="multiselect-option">
                                                <input type="checkbox" name="specialized_services_types[]" value="sistemas"
                                                    {{ in_array('sistemas', old('specialized_services_types', [])) ? 'checked' : '' }}>
                                                <span class="checkmark"></span>Servicios de sistemas/TI
                                            </label>
                                            <label class="multiselect-option">
                                                <input type="checkbox" name="specialized_services_types[]" value="otros" id="otros_checkbox_select"
                                                    {{ in_array('otros', old('specialized_services_types', [])) ? 'checked' : '' }}>
                                                <span class="checkmark"></span>Otros
                                            </label>
                                        </div>
                                    </div>
                                    <div class="input-hint">Puede seleccionar múltiples servicios</div>
                                    <x-input-error :messages="$viewErrors->get('specialized_services_types')" class="input-error" />
                                </div>

                                <div id="otros-input-custom" class="form-group full" style="display: none;">
                                    <x-input-label for="otros_descripcion_custom" :value="__('Especifique otros servicios')" class="form-label" />
                                    <x-text-input id="otros_descripcion_custom" class="reg-input" type="text"
                                        name="otros_descripcion" :value="old('otros_descripcion')"
                                        placeholder="Describa los otros servicios especializados..." />
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ===== SHARED FOOTER ===== -->
                <div class="card-footer">
                    <div>
                        <a id="login-link" class="footer-link" href="{{ route('login') }}">
                            {{ __('¿Ya tienes cuenta? Inicia sesión') }}
                        </a>
                        <button type="button" id="back-btn" class="btn-secondary" style="display:none">
                            {{ __('Atrás') }}
                        </button>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" id="next-btn" class="btn-primary">
                            {{ __('Siguiente') }} <span class="btn-arrow">→</span>
                        </button>
                        <button type="submit" id="submit-btn" class="btn-primary" style="display:none">
                            {{ __('Registrar Proveedor') }} <span class="btn-arrow">→</span>
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>

    @php
        $supplierRegisterFiscalState = [
            'personType' => old('person_type'),
            'taxRegimes' => collect(old('tax_regimes', []))
                ->map(fn ($regime) => is_array($regime) ? ($regime['code'] ?? null) : $regime)
                ->filter()
                ->values()
                ->all(),
            'economicActivities' => (function () {
                $activities = old('economic_activity', ['']);
                return is_array($activities) && count($activities) > 0 ? $activities : [''];
            })(),
            'errors' => [
                'person_type' => $viewErrors->get('person_type'),
                'tax_regimes' => array_merge($viewErrors->get('tax_regimes'), $viewErrors->get('tax_regimes.*.code')),
                'economic_activity' => array_merge($viewErrors->get('economic_activity'), $viewErrors->get('economic_activity.*')),
            ],
        ];
    @endphp

    <script>
        window.__supplierRegisterInitialStep = @json((int) session('supplier_registration_step', 1));
        window.__supplierRegisterFiscalCatalog = @json([
            'fisica' => \App\Support\SupplierFiscalCatalog::regimesFor('fisica'),
            'moral' => \App\Support\SupplierFiscalCatalog::regimesFor('moral'),
        ]);
        window.__supplierRegisterFiscalState = @json($supplierRegisterFiscalState);
    </script>

    <script>
        (function () {
            const form = document.getElementById('supplier-form');
            const steps = Array.from(document.querySelectorAll('[data-step]'));
            const nextBtn = document.getElementById('next-btn');
            const backBtn = document.getElementById('back-btn');
            const bar = document.getElementById('bar');
            const dot1 = document.getElementById('dot-1');
            const dot2 = document.getElementById('dot-2');
            const dot3 = document.getElementById('dot-3');

            let current = Number(window.__supplierRegisterInitialStep || 1);
            const totalSteps = 3;

            // ====== HELPERS DE ERROR INLINE ======
            function showFieldError(field, message) {
                field.style.borderColor = '#ef4444';
                const container = field.closest('.form-group') || field.parentNode;
                const existing = container.querySelector('.js-val-error');
                if (existing) { existing.textContent = message; return; }
                const div = document.createElement('div');
                div.className = 'input-error js-val-error';
                div.textContent = message;
                container.appendChild(div);
            }

            function clearFieldError(field) {
                field.style.borderColor = '';
                const container = field.closest('.form-group') || field.parentNode;
                const existing = container.querySelector('.js-val-error');
                if (existing) existing.remove();
            }

            function renderInlineError(container, messages) {
                if (!container || !Array.isArray(messages) || messages.length === 0) return;
                const div = document.createElement('div');
                div.className = 'input-error js-server-error';
                div.textContent = messages[0];
                container.appendChild(div);
            }

            function setupFiscalFields() {
                const fiscalState = window.__supplierRegisterFiscalState || {};
                const fiscalCatalog = window.__supplierRegisterFiscalCatalog || {};
                const legacyTaxSelect = document.getElementById('tax_regime');
                const legacyTaxGroup = legacyTaxSelect?.closest('.form-group');
                const legacyEconomicInput = document.getElementById('economic_activity');
                const legacyEconomicGroup = legacyEconomicInput?.closest('.form-group');

                if (legacyTaxSelect) {
                    legacyTaxSelect.disabled = true;
                    legacyTaxSelect.removeAttribute('name');
                }

                if (legacyTaxGroup) {
                    legacyTaxGroup.innerHTML = `
                        <label for="person_type" class="form-label">Tipo de persona</label>
                        <select id="person_type" name="person_type" class="reg-input" data-required="1">
                            <option value="" ${fiscalState.personType ? '' : 'selected'} disabled>Selecciona una opción</option>
                            <option value="fisica" ${fiscalState.personType === 'fisica' ? 'selected' : ''}>Persona física</option>
                            <option value="moral" ${fiscalState.personType === 'moral' ? 'selected' : ''}>Persona moral</option>
                            <option value="extranjero" ${fiscalState.personType === 'extranjero' ? 'selected' : ''}>Extranjero</option>
                        </select>
                    `;
                    renderInlineError(legacyTaxGroup, fiscalState.errors?.person_type);
                }

                if (legacyEconomicInput) {
                    legacyEconomicInput.disabled = true;
                    legacyEconomicInput.removeAttribute('name');
                }

                if (legacyEconomicGroup) {
                    const selectedCodes = new Set(Array.isArray(fiscalState.taxRegimes) ? fiscalState.taxRegimes : []);
                    const groupsHtml = ['fisica', 'moral'].map((personType) => {
                        const regimes = fiscalCatalog[personType] || [];
                        const options = regimes.map((regime) => `
                            <label class="multiselect-option" style="position: static; border: 1px solid #dee2e6; border-radius: 7px; padding: 10px 12px;">
                                <input type="checkbox" name="tax_regimes[]" value="${regime.code}" ${selectedCodes.has(regime.code) ? 'checked' : ''}>
                                <span class="checkmark"></span>${regime.code} - ${regime.label}
                            </label>
                        `).join('');

                        return `
                            <div class="full tax-regime-group" data-person-type="${personType}" style="display:none;">
                                <div class="input-hint" style="margin-bottom: 8px;">Selecciona uno o varios regímenes SAT aplicables.</div>
                                <div style="display:grid; gap:8px;">${options}</div>
                            </div>
                        `;
                    }).join('');

                    const activities = Array.isArray(fiscalState.economicActivities) && fiscalState.economicActivities.length > 0
                        ? fiscalState.economicActivities
                        : [''];
                    const rowsHtml = activities.map((activity) => `
                        <div class="activity-row" style="display:flex; gap:10px; align-items:flex-start;">
                            <input class="reg-input activity-input" type="text" name="economic_activity[]" value="${String(activity ?? '').replace(/"/g, '&quot;')}" data-required="1" placeholder="Tal como aparece en la constancia de situación fiscal">
                            <button type="button" class="btn-secondary activity-remove" style="padding: 9px 12px; min-width: 44px;">-</button>
                        </div>
                    `).join('');

                    const taxGroup = document.createElement('div');
                    taxGroup.className = 'form-group full';
                    taxGroup.innerHTML = `
                        <label class="form-label">Regímenes fiscales</label>
                        <div id="tax-regimes-wrapper" class="repse-container" data-selected-person-type="${fiscalState.personType || ''}">
                            ${groupsHtml}
                            <div id="tax-regimes-foreign-note" class="full input-hint" style="display:none;">
                                Los proveedores extranjeros no capturan regímenes fiscales SAT en este formulario.
                            </div>
                        </div>
                    `;

                    legacyEconomicGroup.parentNode.insertBefore(taxGroup, legacyEconomicGroup);
                    renderInlineError(taxGroup, fiscalState.errors?.tax_regimes);

                    legacyEconomicGroup.innerHTML = `
                        <label class="form-label">Actividades económicas</label>
                        <div id="economic-activity-list" style="display:grid; gap:10px;">${rowsHtml}</div>
                        <button type="button" id="add-economic-activity" class="btn-secondary" style="margin-top: 10px;">+ Agregar actividad</button>
                        <div class="input-hint">Captura una o varias actividades tal como aparecen en la constancia de situación fiscal.</div>
                    `;
                    renderInlineError(legacyEconomicGroup, fiscalState.errors?.economic_activity);
                }

                const personTypeSelect = document.getElementById('person_type');
                const taxGroups = () => Array.from(document.querySelectorAll('.tax-regime-group'));
                const foreignNote = document.getElementById('tax-regimes-foreign-note');

                function refreshTaxRegimeVisibility() {
                    const selectedType = personTypeSelect?.value || '';
                    taxGroups().forEach((group) => {
                        const shouldShow = group.dataset.personType === selectedType;
                        group.style.display = shouldShow ? 'block' : 'none';
                        group.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                            if (!shouldShow) checkbox.checked = false;
                        });
                    });

                    if (foreignNote) {
                        foreignNote.style.display = selectedType === 'extranjero' ? 'block' : 'none';
                    }
                }

                personTypeSelect?.addEventListener('change', refreshTaxRegimeVisibility);
                refreshTaxRegimeVisibility();

                const activityList = document.getElementById('economic-activity-list');
                const addActivityBtn = document.getElementById('add-economic-activity');

                function buildActivityRow(value = '') {
                    const row = document.createElement('div');
                    row.className = 'activity-row';
                    row.style.display = 'flex';
                    row.style.gap = '10px';
                    row.style.alignItems = 'flex-start';
                    row.innerHTML = `
                        <input class="reg-input activity-input" type="text" name="economic_activity[]" value="${String(value).replace(/"/g, '&quot;')}" data-required="1" placeholder="Tal como aparece en la constancia de situación fiscal">
                        <button type="button" class="btn-secondary activity-remove" style="padding: 9px 12px; min-width: 44px;">-</button>
                    `;
                    return row;
                }

                function refreshActivityButtons() {
                    const rows = activityList ? Array.from(activityList.querySelectorAll('.activity-row')) : [];
                    rows.forEach((row) => {
                        const removeBtn = row.querySelector('.activity-remove');
                        if (removeBtn) removeBtn.disabled = rows.length === 1;
                    });
                }

                addActivityBtn?.addEventListener('click', () => {
                    if (!activityList) return;
                    activityList.appendChild(buildActivityRow(''));
                    refreshActivityButtons();
                });

                activityList?.addEventListener('click', (event) => {
                    const target = event.target;
                    if (!(target instanceof HTMLElement) || !target.classList.contains('activity-remove')) return;
                    const rows = activityList.querySelectorAll('.activity-row');
                    if (rows.length === 1) {
                        const input = rows[0].querySelector('input');
                        if (input) input.value = '';
                        return;
                    }
                    target.closest('.activity-row')?.remove();
                    refreshActivityButtons();
                });

                refreshActivityButtons();
            }

            setupFiscalFields();

            // ====== ENFORCERS ======
            const rfcEl = document.getElementById('rfc');
            const RFC_REGEX = /^([A-ZÑ&]{3,4})\d{6}[A-Z0-9]{3}$/;
            if (rfcEl) {
                rfcEl.addEventListener('keydown', (e) => { if (e.key === ' ') e.preventDefault(); });
                rfcEl.addEventListener('input', (e) => {
                    let v = e.target.value.toUpperCase();
                    v = v.replace(/\s+/g, '').replace(/[^A-Z0-9Ñ&]/g, '');
                    e.target.value = v;
                    e.target.setCustomValidity('');
                    clearFieldError(rfcEl);
                });
                rfcEl.addEventListener('blur', () => {
                    const v = rfcEl.value.trim();
                    if (v.length > 0 && !RFC_REGEX.test(v)) {
                        rfcEl.setCustomValidity('RFC inválido.');
                        showFieldError(rfcEl, 'RFC inválido. Debe tener 3–4 letras + 6 dígitos de fecha (AAMMDD) + 3 alfanuméricos. Ej: XAXX010101000');
                    } else if (v.length === 0) {
                        rfcEl.setCustomValidity('');
                        // No limpiar aquí; el submit mostrará "obligatorio" si aplica
                    } else {
                        rfcEl.setCustomValidity('');
                        clearFieldError(rfcEl);
                    }
                });
            }

            const phoneInputs = Array.from(document.querySelectorAll('[data-phone="10"]'));
            function sanitizePhone(el) {
                let v = el.value.replace(/\D/g, '');
                if (v.length > 10) v = v.slice(0, 10);
                el.value = v;
            }
            phoneInputs.forEach((el) => {
                el.addEventListener('keydown', (e) => { if (e.key === ' ') e.preventDefault(); });
                el.addEventListener('input', () => { sanitizePhone(el); el.setCustomValidity(''); clearFieldError(el); });
                el.addEventListener('blur', () => {
                    const v = el.value;
                    if (v.length === 0 && el.id === 'contact_phone') { el.setCustomValidity(''); clearFieldError(el); return; }
                    if (v.length === 0) return; // campo requerido: el submit mostrará "obligatorio"
                    if (!/^\d{10}$/.test(v)) {
                        el.setCustomValidity('Debe tener exactamente 10 dígitos.');
                        showFieldError(el, 'El teléfono debe tener exactamente 10 dígitos, sin espacios ni guiones. Ej: 5512345678');
                    } else {
                        el.setCustomValidity('');
                        clearFieldError(el);
                    }
                });
            });

            // ====== REPSE ======
            function updateConditionalRequired() {
                const repseYes = document.getElementById('repse_yes');
                const repseRequired = repseYes && repseYes.checked;
                document.querySelectorAll('[data-conditional-required="repse"]').forEach(field => {
                    field.required = repseRequired;
                    if (repseRequired) {
                        field.setAttribute('data-required', '1');
                    } else {
                        field.removeAttribute('data-required');
                        field.required = false;
                    }
                });
                const serviceCheckboxes = document.querySelectorAll('input[name="specialized_services_types[]"]');
                const atLeastOneChecked = Array.from(serviceCheckboxes).some(cb => cb.checked);
                if (repseRequired && !atLeastOneChecked) {
                    if (serviceCheckboxes.length > 0) {
                        serviceCheckboxes[0].setCustomValidity('Debe seleccionar al menos un tipo de servicio especializado');
                    }
                } else {
                    serviceCheckboxes.forEach(cb => cb.setCustomValidity(''));
                }
            }

            document.querySelectorAll('input[name="provides_specialized_services"]').forEach(radio => {
                radio.addEventListener('change', updateConditionalRequired);
            });
            document.querySelectorAll('input[name="specialized_services_types[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', updateConditionalRequired);
            });

            const repseExpiryDate = document.getElementById('repse_expiry_date');
            if (repseExpiryDate) {
                repseExpiryDate.addEventListener('blur', function() {
                    const selectedDate = new Date(this.value);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    if (this.value && selectedDate <= today) {
                        this.setCustomValidity('La fecha de vencimiento debe ser posterior a hoy');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }

            const repseNumber = document.getElementById('repse_registration_number');
            if (repseNumber) {
                repseNumber.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
                repseNumber.addEventListener('blur', function() { this.setCustomValidity(''); });
            }

            // ====== STEP MANAGEMENT ======
            function syncRequired() {
                form.querySelectorAll('[data-required], [required]').forEach(el => el.required = false);
                const currentStepEl = steps.find(s => Number(s.dataset.step) === current);
                if (currentStepEl) {
                    currentStepEl.querySelectorAll('[data-required]').forEach(el => el.required = true);
                }
                updateConditionalRequired();
            }

            function updateStepIndicator() {
                const c1 = document.getElementById('connector-1');
                const c2 = document.getElementById('connector-2');
                const pf = document.getElementById('progress-fill');
                const l1 = document.getElementById('label-1');
                const l2 = document.getElementById('label-2');
                const l3 = document.getElementById('label-3');

                [dot1, dot2, dot3].forEach((d, i) => {
                    if (d) d.className = i + 1 === current ? 'step-dot active' : 'step-dot inactive';
                });
                [l1, l2, l3].forEach((l, i) => {
                    if (l) l.className = i + 1 === current ? 'step-label active' : 'step-label';
                });
                if (pf) pf.style.width = current >= 2 ? '100%' : '0%';
                if (c1) c1.classList.toggle('done', current >= 2);
                if (c2) c2.classList.toggle('done', current >= 3);

                // Footer buttons
                const loginLink = document.getElementById('login-link');
                const nextBtnEl = document.getElementById('next-btn');
                const submitBtnEl = document.getElementById('submit-btn');
                const backBtnEl = document.getElementById('back-btn');
                if (loginLink) loginLink.style.display = current === 1 ? '' : 'none';
                if (backBtnEl) backBtnEl.style.display = current > 1 ? 'inline-flex' : 'none';
                if (nextBtnEl) nextBtnEl.style.display = current < totalSteps ? 'inline-flex' : 'none';
                if (submitBtnEl) submitBtnEl.style.display = current === totalSteps ? 'inline-flex' : 'none';

                if (bar) bar.style.width = ((current - 1) / (totalSteps - 1) * 100) + '%';
            }

            function showStep(step) {
                current = step;
                window.__supplierRegisterCurrentStep = step;
                steps.forEach(s => s.classList.toggle('hidden', Number(s.dataset.step) !== step));
                updateStepIndicator();
                syncRequired();
                const currentStepEl = steps.find(s => Number(s.dataset.step) === step);
                if (currentStepEl) {
                    const firstInput = currentStepEl.querySelector('input, select, textarea');
                    if (firstInput) firstInput.focus();
                }
            }

            function validateCurrentStep() {
                let valid = true;
                const currentStepEl = steps.find(s => Number(s.dataset.step) === current);
                if (!currentStepEl) return true;

                // Limpiar errores inline previos de este paso
                currentStepEl.querySelectorAll('.js-val-error').forEach(e => e.remove());

                // Disparar blur para activar setCustomValidity en enforcers (RFC, teléfonos, etc.)
                currentStepEl.querySelectorAll('input, select, textarea').forEach(el => {
                    el.dispatchEvent(new Event('blur'));
                });

                // --- Campos obligatorios: mostrar error en TODOS, no solo el primero ---
                const requiredFields = currentStepEl.querySelectorAll('[data-required="1"], [required]');
                requiredFields.forEach(el => {
                    const value = (el.value || '').trim();

                    if (!value) {
                        valid = false;
                        showFieldError(el, 'Este campo es obligatorio.');
                        return;
                    }

                    // Validaciones específicas que el browser no cubre nativamente
                    if (el.id === 'password') {
                        const hasMinLength = value.length >= 8;
                        const hasUpper     = /[A-Z]/.test(value);
                        const hasLower     = /[a-z]/.test(value);
                        const hasNumber    = /[0-9]/.test(value);
                        const hasSymbol    = /[^A-Za-z0-9]/.test(value);
                        if (!hasMinLength || !hasUpper || !hasLower || !hasNumber || !hasSymbol) {
                            valid = false;
                            showFieldError(el, 'La contraseña debe tener mínimo 8 caracteres, una mayúscula, una minúscula, un número y un símbolo especial.');
                            return;
                        }
                    }
                    if (el.id === 'password_confirmation') {
                        const pw = document.getElementById('password');
                        if (pw && value !== pw.value) {
                            valid = false;
                            showFieldError(el, 'Las contraseñas no coinciden. Verifica que ambas sean iguales.');
                            return;
                        }
                    }

                    // Validaciones nativas del browser (email, pattern, etc.)
                    if (!el.checkValidity()) {
                        valid = false;
                        let msg = el.validationMessage;
                        if (el.id === 'rfc') {
                            msg = 'RFC inválido. Debe tener 3–4 letras + 6 dígitos de fecha (AAMMDD) + 3 alfanuméricos. Ej: XAXX010101000';
                        } else if (el.type === 'email') {
                            msg = 'Ingresa un correo electrónico válido. Ej: nombre@empresa.com';
                        } else if (el.dataset.phone === '10') {
                            msg = 'El teléfono debe tener exactamente 10 dígitos, sin espacios ni guiones. Ej: 5512345678';
                        }
                        showFieldError(el, msg);
                    } else {
                        clearFieldError(el);
                    }
                });

                // --- Radio: ¿presta servicios especializados? ---
                const personTypeSelect = document.getElementById('person_type');
                if (personTypeSelect && currentStepEl.contains(personTypeSelect)) {
                    const selectedType = personTypeSelect.value;
                    const taxRegimeCheckboxes = Array.from(document.querySelectorAll('input[name="tax_regimes[]"]'));
                    const checkedTaxRegimes = taxRegimeCheckboxes.filter((checkbox) => checkbox.checked);

                    if (selectedType && selectedType !== 'extranjero' && checkedTaxRegimes.length === 0) {
                        valid = false;
                        const taxRegimesWrapper = document.getElementById('tax-regimes-wrapper');
                        const container = taxRegimesWrapper && (taxRegimesWrapper.closest('.form-group') || taxRegimesWrapper.parentNode);
                        if (container && !container.querySelector('.js-val-error')) {
                            const div = document.createElement('div');
                            div.className = 'input-error js-val-error';
                            div.textContent = 'Selecciona al menos un régimen fiscal SAT.';
                            container.appendChild(div);
                        }
                    }
                }

                const radios = currentStepEl.querySelectorAll('input[name="provides_specialized_services"]');
                if (radios.length > 0 && !Array.from(radios).some(r => r.checked)) {
                    valid = false;
                    const radioGroup = currentStepEl.querySelector('.radio-group');
                    const container = (radioGroup && (radioGroup.closest('.form-group') || radioGroup.parentNode));
                    if (container && !container.querySelector('.js-val-error')) {
                        const div = document.createElement('div');
                        div.className = 'input-error js-val-error';
                        div.textContent = 'Debes indicar si prestas servicios especializados (Sí o No).';
                        container.appendChild(div);
                    }
                }

                // --- Checkboxes REPSE (si aplica) ---
                const repseYes = document.getElementById('repse_yes');
                if (repseYes && repseYes.checked && currentStepEl.contains(repseYes)) {
                    const serviceCheckboxes = currentStepEl.querySelectorAll('input[name="specialized_services_types[]"]');
                    const atLeastOneChecked = Array.from(serviceCheckboxes).some(cb => cb.checked);
                    if (!atLeastOneChecked) {
                        valid = false;
                        const multiselectContainer = currentStepEl.querySelector('#custom-multiselect');
                        const container = multiselectContainer && (multiselectContainer.closest('.form-group') || multiselectContainer.parentNode);
                        if (container && !container.querySelector('.js-val-error')) {
                            const div = document.createElement('div');
                            div.className = 'input-error js-val-error';
                            div.textContent = 'Selecciona al menos un tipo de servicio especializado.';
                            container.appendChild(div);
                        }
                    }
                }

                return valid;
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    current = Number(window.__supplierRegisterCurrentStep || current);
                    if (!validateCurrentStep()) return;
                    if (current < totalSteps) showStep(current + 1);
                });
            }
            if (backBtn) {
                backBtn.addEventListener('click', function () {
                    current = Number(window.__supplierRegisterCurrentStep || current);
                    if (current > 1) showStep(current - 1);
                });
            }

            form.addEventListener('submit', async function (e) {
                // Siempre prevenir envío nativo; nosotros controlamos el submit
                e.preventDefault();
                current = Number(window.__supplierRegisterCurrentStep || current);

                // 1) Validar el paso actual primero (muestra errores inline)
                if (!validateCurrentStep()) return;

                // 2) Validar TODOS los pasos antes de enviar
                let allValid = true;
                let firstInvalidStep = null;
                const savedStep = current;
                for (let i = 1; i <= totalSteps; i++) {
                    current = i;
                    if (!validateCurrentStep()) {
                        allValid = false;
                        if (firstInvalidStep === null) firstInvalidStep = i;
                    }
                }
                current = savedStep;

                if (!allValid) {
                    showStep(firstInvalidStep);
                    return;
                }

                // 3) Refrescar el token CSRF justo antes de enviar para evitar error 419
                try {
                    const res = await fetch('/csrf-refresh', { credentials: 'same-origin' });
                    if (res.ok) {
                        const data = await res.json();
                        const tokenInput = form.querySelector('input[name="_token"]');
                        if (tokenInput) tokenInput.value = data.token;
                        const metaToken = document.querySelector('meta[name="csrf-token"]');
                        if (metaToken) metaToken.setAttribute('content', data.token);
                    }
                } catch (err) { /* continuar de todas formas */ }

                // 4) Deshabilitar botón para prevenir doble envío
                const submitBtnEl = document.getElementById('submit-btn');
                if (submitBtnEl) {
                    submitBtnEl.disabled = true;
                    submitBtnEl.innerHTML = 'Registrando... <span class="btn-arrow">⟳</span>';
                }

                // 5) Enviar el formulario
                form.submit();
            });

            if (rfcEl) {
                rfcEl.addEventListener('input', () => rfcEl.value = rfcEl.value.toUpperCase());
            }

            // Inicialización
            showStep(current);
            updateConditionalRequired();
        })();

        // ====== REPSE UX ======
        document.addEventListener('DOMContentLoaded', function() {
            const repseFields = document.getElementById('repse-fields');
            const repseRadios = document.querySelectorAll('input[name="provides_specialized_services"]');

            repseRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    const showFields = document.getElementById('repse_yes').checked;
                    if (showFields) {
                        repseFields.style.display = 'grid';
                        repseFields.style.opacity = '0';
                        repseFields.style.transform = 'translateY(-8px)';
                        setTimeout(() => {
                            repseFields.style.transition = 'all 0.3s ease';
                            repseFields.style.opacity = '1';
                            repseFields.style.transform = 'translateY(0)';
                        }, 10);
                    } else {
                        repseFields.style.transition = 'all 0.3s ease';
                        repseFields.style.opacity = '0';
                        repseFields.style.transform = 'translateY(-8px)';
                        setTimeout(() => { repseFields.style.display = 'none'; }, 300);
                    }
                });
            });

            const serviceCheckboxes = document.querySelectorAll('input[name="specialized_services_types[]"]');
            serviceCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const atLeastOneChecked = Array.from(serviceCheckboxes).some(cb => cb.checked);
                    if (atLeastOneChecked) serviceCheckboxes.forEach(cb => cb.setCustomValidity(''));

                    if (this.value === 'otros') {
                        const otrosInput = document.getElementById('otros-input-custom');
                        if (otrosInput) otrosInput.style.display = this.checked ? 'block' : 'none';
                    }
                });
            });

            const repseNumber = document.getElementById('repse_registration_number');
            if (repseNumber) {
                repseNumber.addEventListener('input', function() {
                    let value = this.value.toUpperCase();
                    if (value.length > 0 && !value.startsWith('REPSE-') && /^\d/.test(value)) {
                        value = 'REPSE-' + value.replace(/[^0-9]/g, '');
                    }
                    this.value = value;
                });
            }

            const repseExpiryDate = document.getElementById('repse_expiry_date');
            if (repseExpiryDate) {
                repseExpiryDate.addEventListener('change', function() {
                    const selectedDate = new Date(this.value);
                    const today = new Date();
                    const threeMonthsFromNow = new Date();
                    threeMonthsFromNow.setMonth(today.getMonth() + 3);
                    this.classList.remove('date-warning', 'date-error');
                    if (selectedDate <= today) {
                        this.classList.add('date-error');
                        this.setCustomValidity('La fecha de vencimiento debe ser posterior a hoy');
                    } else if (selectedDate <= threeMonthsFromNow) {
                        this.classList.add('date-warning');
                        this.setCustomValidity('');
                        this.title = 'Advertencia: El registro vence en menos de 3 meses';
                    } else {
                        this.setCustomValidity('');
                        this.title = '';
                    }
                });
            }

            const dateStyles = document.createElement('style');
            dateStyles.textContent = `
                .date-warning { border-color: #f59e0b !important; background-color: #fef3c7; }
                .date-error { border-color: #ef4444 !important; background-color: #fee2e2; }
            `;
            document.head.appendChild(dateStyles);

            // Server errors: show the step with errors and reveal REPSE if needed
            const errorFields = document.querySelectorAll('.input-error');
            if (errorFields.length > 0 && repseFields) {
                errorFields.forEach(error => {
                    const repseError = error.closest('#repse-fields');
                    if (repseError) {
                        const repseYes = document.getElementById('repse_yes');
                        if (repseYes) repseYes.checked = true;
                        repseFields.style.display = 'grid';
                    }
                });
            }

            // Init multiselect text
            updateSelectedText();
            const otrosChecked = document.querySelector('#custom-multiselect input[value="otros"]:checked');
            if (otrosChecked) {
                const otrosInput = document.getElementById('otros-input-custom');
                if (otrosInput) otrosInput.style.display = 'block';
            }
        });
    </script>
    <script>
        // Multiselect personalizado
        function toggleDropdown() {
            const header = document.querySelector('.multiselect-header');
            const options = document.getElementById('multiselect-options');
            header.classList.toggle('active');
            options.classList.toggle('show');
        }

        document.addEventListener('click', function(e) {
            const multiselect = document.getElementById('custom-multiselect');
            if (multiselect && !multiselect.contains(e.target)) {
                const header = document.querySelector('.multiselect-header');
                const options = document.getElementById('multiselect-options');
                if (header) header.classList.remove('active');
                if (options) options.classList.remove('show');
            }
        });

        function updateSelectedText() {
            const checkboxes = document.querySelectorAll('#custom-multiselect input[type="checkbox"]');
            const selectedText = document.getElementById('selected-text');
            if (!selectedText) return;
            const checked = Array.from(checkboxes).filter(cb => cb.checked);
            if (checked.length === 0) {
                selectedText.textContent = 'Seleccionar servicios...';
                selectedText.style.color = '#9ca3af';
            } else if (checked.length === 1) {
                selectedText.textContent = checked[0].parentElement.textContent.trim();
                selectedText.style.color = '#111827';
            } else {
                selectedText.textContent = `${checked.length} servicios seleccionados`;
                selectedText.style.color = '#111827';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const customCheckboxes = document.querySelectorAll('#custom-multiselect input[type="checkbox"]');
            customCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectedText();
                    if (this.value === 'otros') {
                        const otrosInput = document.getElementById('otros-input-custom');
                        if (otrosInput) otrosInput.style.display = this.checked ? 'block' : 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>
