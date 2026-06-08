<?php

namespace App\Http\Requests;

use App\Enum\PaymentTerm;
use App\Rules\EfosNotListed;
use App\Rules\ValidRfc;
use App\Support\SupplierFiscalCatalog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RegisterSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:suppliers,email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],

            'company_name' => ['required', 'string', 'max:255'],
            'rfc' => ['required', 'string', 'max:13', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', 'unique:suppliers,rfc', new ValidRfc, new EfosNotListed],
            'supplier_type' => ['required', 'in:product,service,product_service'],
            'person_type' => ['required', Rule::in(array_keys(SupplierFiscalCatalog::personTypes()))],
            'tax_regimes' => ['nullable', 'array'],
            'tax_regimes.*.code' => ['required_with:tax_regimes', 'string'],
            'tax_regimes.*.label' => ['required_with:tax_regimes', 'string'],
            'address' => ['required', 'string', 'max:1000'],
            'postal_code' => ['required', 'string', 'regex:/^\d{5}$/'],
            'phone_number' => ['required', 'string', 'regex:/^\d{10}$/'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'regex:/^\d{10}$/'],

            'provides_specialized_services' => ['required', 'boolean'],
            'repse_registration_number' => ['required_if:provides_specialized_services,1', 'nullable', 'string', 'max:50'],
            'repse_expiry_date' => ['required_if:provides_specialized_services,1', 'nullable', 'date', 'after:today'],
            'specialized_services_types' => ['required_if:provides_specialized_services,1', 'nullable', 'array', 'min:1'],
            'specialized_services_types.*' => ['string', Rule::in([
                'limpieza',
                'vigilancia',
                'mantenimiento',
                'alimentacion',
                'contabilidad',
                'sistemas',
                'otros',
            ])],
            'otros_descripcion' => ['nullable', 'string', 'max:255', 'required_if:specialized_services_types.*,otros'],
            'economic_activity' => ['required', 'array', 'min:1'],
            'economic_activity.*' => ['required', 'string', 'max:150'],
            'default_payment_terms' => ['required', Rule::in(array_column(PaymentTerm::cases(), 'value'))],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es obligatorio.',
            'last_name.required' => 'Los apellidos son obligatorios.',
            'email.required' => 'El correo electronico es obligatorio.',
            'email.email' => 'Debe ser un correo electronico valido.',
            'email.unique' => 'Este correo ya esta registrado.',
            'password.required' => 'La contrasena es obligatoria.',
            'password.min' => 'La contrasena debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contrasenas no coinciden.',

            'company_name.required' => 'La razon social es obligatoria.',
            'rfc.required' => 'El RFC es obligatorio.',
            'rfc.regex' => 'El formato del RFC no es valido.',
            'address.required' => 'La direccion es obligatoria.',
            'postal_code.required' => 'El codigo postal es obligatorio.',
            'postal_code.regex' => 'El codigo postal debe tener exactamente 5 digitos.',
            'phone_number.required' => 'El telefono de la empresa es obligatorio.',
            'phone_number.regex' => 'El telefono de la empresa debe tener exactamente 10 digitos numericos sin espacios ni guiones.',
            'contact_person.required' => 'La persona de contacto es obligatoria.',
            'contact_phone.regex' => 'El telefono de contacto debe tener exactamente 10 digitos numericos sin espacios ni guiones.',
            'supplier_type.required' => 'El tipo de proveedor es obligatorio.',
            'supplier_type.in' => 'Seleccione un tipo de proveedor valido.',
            'person_type.required' => 'El tipo de persona es obligatorio.',
            'person_type.in' => 'Seleccione un tipo de persona valido.',
            'tax_regimes.array' => 'Los regimenes fiscales deben enviarse como una lista valida.',
            'tax_regimes.*.code.required_with' => 'Cada regimen fiscal debe incluir una clave SAT.',
            'tax_regimes.*.label.required_with' => 'Cada regimen fiscal debe incluir una descripcion.',
            'economic_activity.required' => 'Debe capturar al menos una actividad economica.',
            'economic_activity.array' => 'Las actividades economicas deben enviarse como una lista valida.',
            'economic_activity.min' => 'Debe capturar al menos una actividad economica.',
            'economic_activity.*.required' => 'Las actividades economicas no pueden estar vacias.',
            'economic_activity.*.max' => 'Cada actividad economica no puede exceder 150 caracteres.',

            'provides_specialized_services.required' => 'Debe indicar si presta servicios especializados.',
            'provides_specialized_services.boolean' => 'Debe seleccionar si o no para servicios especializados.',
            'repse_registration_number.required_if' => 'El numero de registro REPSE es obligatorio para proveedores de servicios especializados.',
            'repse_registration_number.max' => 'El numero REPSE no puede exceder 50 caracteres.',
            'repse_expiry_date.required_if' => 'La fecha de vencimiento REPSE es obligatoria para proveedores de servicios especializados.',
            'repse_expiry_date.date' => 'Debe ser una fecha valida.',
            'repse_expiry_date.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
            'specialized_services_types.required_if' => 'Debe seleccionar al menos un tipo de servicio especializado.',
            'specialized_services_types.array' => 'Los tipos de servicios deben ser una lista valida.',
            'specialized_services_types.min' => 'Debe seleccionar al menos un tipo de servicio.',
            'specialized_services_types.*.in' => 'Uno o mas tipos de servicios seleccionados no son validos.',
            'otros_descripcion.required_if' => 'Debe especificar que otros servicios ofrece.',
            'otros_descripcion.max' => 'La descripcion de otros servicios no puede exceder 255 caracteres.',
            'default_payment_terms.required' => 'Las condiciones de pago son obligatorias.',
            'default_payment_terms.in' => 'Las condiciones de pago seleccionadas no son validas.',
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellidos',
            'email' => 'correo electronico',
            'password' => 'contrasena',
            'company_name' => 'razon social',
            'rfc' => 'RFC',
            'address' => 'direccion',
            'postal_code' => 'codigo postal',
            'phone_number' => 'telefono de empresa',
            'contact_person' => 'persona de contacto',
            'contact_phone' => 'telefono de contacto',
            'supplier_type' => 'tipo de proveedor',
            'person_type' => 'tipo de persona',
            'tax_regimes' => 'regimenes fiscales',
            'provides_specialized_services' => 'servicios especializados',
            'repse_registration_number' => 'numero de registro REPSE',
            'repse_expiry_date' => 'fecha de vencimiento REPSE',
            'specialized_services_types' => 'tipos de servicios especializados',
            'otros_descripcion' => 'descripcion de otros servicios',
            'economic_activity' => 'actividades economicas',
            'default_payment_terms' => 'condiciones de pago',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('provides_specialized_services')) {
            $this->merge([
                'provides_specialized_services' => filter_var(
                    $this->provides_specialized_services,
                    FILTER_VALIDATE_BOOLEAN
                ),
            ]);
        }

        if ($this->has('rfc')) {
            $this->merge([
                'rfc' => strtoupper(trim((string) $this->rfc)),
            ]);
        }

        if ($this->has('phone_number')) {
            $this->merge([
                'phone_number' => preg_replace('/\D/', '', (string) $this->phone_number),
            ]);
        }

        if ($this->has('postal_code')) {
            $this->merge([
                'postal_code' => preg_replace('/\D/', '', (string) $this->postal_code),
            ]);
        }

        if ($this->filled('contact_phone')) {
            $this->merge([
                'contact_phone' => preg_replace('/\D/', '', (string) $this->contact_phone),
            ]);
        }

        $personType = $this->input('person_type');

        if ($this->has('tax_regimes')) {
            $taxRegimes = $this->input('tax_regimes');
            $taxRegimes = is_array($taxRegimes) ? $taxRegimes : [$taxRegimes];

            $this->merge([
                'tax_regimes' => SupplierFiscalCatalog::normalizeSelectedRegimes(
                    is_string($personType) ? $personType : null,
                    $taxRegimes
                ),
            ]);
        }

        if ($this->has('economic_activity')) {
            $activities = $this->input('economic_activity');
            $activities = is_array($activities) ? $activities : [$activities];

            $this->merge([
                'economic_activity' => SupplierFiscalCatalog::normalizeActivities($activities),
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('provides_specialized_services')) {
                $services = $this->input('specialized_services_types', []);

                if (in_array('otros', $services, true) && empty($this->input('otros_descripcion'))) {
                    $validator->errors()->add(
                        'otros_descripcion',
                        'Debe especificar que otros servicios ofrece.'
                    );
                }
            }

            if ($this->filled('repse_registration_number') && $this->boolean('provides_specialized_services')) {
                $number = strtoupper(trim((string) $this->input('repse_registration_number')));

                if (! preg_match('/^REPSE-?\w+$/i', $number)) {
                    $validator->errors()->add(
                        'repse_registration_number',
                        'El formato del numero REPSE no es valido. Debe comenzar con "REPSE-".'
                    );
                }
            }

            $personType = $this->input('person_type');
            $taxRegimes = $this->input('tax_regimes', []);

            if (in_array($personType, ['fisica', 'moral'], true) && empty($taxRegimes)) {
                $validator->errors()->add(
                    'tax_regimes',
                    'Debe seleccionar al menos un regimen fiscal para el tipo de persona capturado.'
                );
            }

            if ($personType === 'extranjero' && ! empty($taxRegimes)) {
                $validator->errors()->add(
                    'tax_regimes',
                    'Los proveedores extranjeros no deben capturar regimenes fiscales SAT.'
                );
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        $response = redirect()
            ->to($this->getRedirectUrl())
            ->withErrors($validator, $this->errorBag)
            ->withInput()
            ->with('supplier_registration_step', $this->resolveStepFromErrors($validator->errors()->keys()));

        throw new HttpResponseException($response);
    }

    private function resolveStepFromErrors(array $errorKeys): int
    {
        $stepMap = [
            1 => [
                'first_name',
                'last_name',
                'email',
                'password',
                'password_confirmation',
            ],
            2 => [
                'company_name',
                'rfc',
                'supplier_type',
                'person_type',
                'tax_regimes',
                'tax_regimes.*',
                'economic_activity',
                'economic_activity.*',
                'address',
                'postal_code',
            ],
            3 => [
                'phone_number',
                'contact_person',
                'contact_phone',
                'default_payment_terms',
                'provides_specialized_services',
                'repse_registration_number',
                'repse_expiry_date',
                'specialized_services_types',
                'specialized_services_types.*',
                'otros_descripcion',
            ],
        ];

        foreach ($stepMap as $step => $fields) {
            foreach ($errorKeys as $errorKey) {
                foreach ($fields as $field) {
                    if ($errorKey === $field || str_starts_with($errorKey, $field . '.')) {
                        return $step;
                    }
                }
            }
        }

        return 1;
    }
}
