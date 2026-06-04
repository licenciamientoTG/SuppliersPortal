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
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],

            'company_name' => ['required', 'string', 'max:255'],
            'rfc' => ['required', 'string', 'max:13', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', 'unique:suppliers,rfc', new ValidRfc, new EfosNotListed],
            'supplier_type' => ['required', 'in:product,service,product_service'],
            'person_type' => ['required', Rule::in(array_keys(SupplierFiscalCatalog::personTypes()))],
            'tax_regimes' => ['nullable', 'array'],
            'tax_regimes.*.code' => ['required_with:tax_regimes', 'string'],
            'tax_regimes.*.label' => ['required_with:tax_regimes', 'string'],
            'address' => ['required', 'string', 'max:1000'],
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
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Debe ser un correo electrónico válido.',
            'email.unique' => 'Este correo ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',

            'company_name.required' => 'La razón social es obligatoria.',
            'rfc.required' => 'El RFC es obligatorio.',
            'rfc.regex' => 'El formato del RFC no es válido.',
            'address.required' => 'La dirección es obligatoria.',
            'phone_number.required' => 'El teléfono de la empresa es obligatorio.',
            'phone_number.regex' => 'El teléfono de la empresa debe tener exactamente 10 dígitos numéricos (sin espacios ni guiones).',
            'contact_person.required' => 'La persona de contacto es obligatoria.',
            'contact_phone.regex' => 'El teléfono de contacto debe tener exactamente 10 dígitos numéricos (sin espacios ni guiones).',
            'supplier_type.required' => 'El tipo de proveedor es obligatorio.',
            'supplier_type.in' => 'Seleccione un tipo de proveedor válido.',
            'person_type.required' => 'El tipo de persona es obligatorio.',
            'person_type.in' => 'Seleccione un tipo de persona válido.',
            'tax_regimes.array' => 'Los regímenes fiscales deben enviarse como una lista válida.',
            'tax_regimes.*.code.required_with' => 'Cada régimen fiscal debe incluir una clave SAT.',
            'tax_regimes.*.label.required_with' => 'Cada régimen fiscal debe incluir una descripción.',
            'economic_activity.required' => 'Debe capturar al menos una actividad económica.',
            'economic_activity.array' => 'Las actividades económicas deben enviarse como una lista válida.',
            'economic_activity.min' => 'Debe capturar al menos una actividad económica.',
            'economic_activity.*.required' => 'Las actividades económicas no pueden estar vacías.',
            'economic_activity.*.max' => 'Cada actividad económica no puede exceder 150 caracteres.',

            'provides_specialized_services.required' => 'Debe indicar si presta servicios especializados.',
            'provides_specialized_services.boolean' => 'Debe seleccionar sí o no para servicios especializados.',
            'repse_registration_number.required_if' => 'El número de registro REPSE es obligatorio para proveedores de servicios especializados.',
            'repse_registration_number.max' => 'El número REPSE no puede exceder 50 caracteres.',
            'repse_expiry_date.required_if' => 'La fecha de vencimiento REPSE es obligatoria para proveedores de servicios especializados.',
            'repse_expiry_date.date' => 'Debe ser una fecha válida.',
            'repse_expiry_date.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
            'specialized_services_types.required_if' => 'Debe seleccionar al menos un tipo de servicio especializado.',
            'specialized_services_types.array' => 'Los tipos de servicios deben ser una lista válida.',
            'specialized_services_types.min' => 'Debe seleccionar al menos un tipo de servicio.',
            'specialized_services_types.*.in' => 'Uno o más tipos de servicios seleccionados no son válidos.',
            'otros_descripcion.required_if' => 'Debe especificar qué otros servicios ofrece.',
            'otros_descripcion.max' => 'La descripción de otros servicios no puede exceder 255 caracteres.',
            'default_payment_terms.required' => 'Las condiciones de pago son obligatorias.',
            'default_payment_terms.in' => 'Las condiciones de pago seleccionadas no son válidas.',
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellidos',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'company_name' => 'razón social',
            'rfc' => 'RFC',
            'address' => 'dirección',
            'phone_number' => 'teléfono de empresa',
            'contact_person' => 'persona de contacto',
            'contact_phone' => 'teléfono de contacto',
            'supplier_type' => 'tipo de proveedor',
            'person_type' => 'tipo de persona',
            'tax_regimes' => 'regímenes fiscales',
            'provides_specialized_services' => 'servicios especializados',
            'repse_registration_number' => 'número de registro REPSE',
            'repse_expiry_date' => 'fecha de vencimiento REPSE',
            'specialized_services_types' => 'tipos de servicios especializados',
            'otros_descripcion' => 'descripción de otros servicios',
            'economic_activity' => 'actividades económicas',
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
                        'Debe especificar qué otros servicios ofrece.'
                    );
                }
            }

            if ($this->filled('repse_registration_number') && $this->boolean('provides_specialized_services')) {
                $number = strtoupper(trim((string) $this->input('repse_registration_number')));

                if (! preg_match('/^REPSE-?\w+$/i', $number)) {
                    $validator->errors()->add(
                        'repse_registration_number',
                        'El formato del número REPSE no es válido. Debe comenzar con "REPSE-".'
                    );
                }
            }

            $personType = $this->input('person_type');
            $taxRegimes = $this->input('tax_regimes', []);

            if (in_array($personType, ['fisica', 'moral'], true) && empty($taxRegimes)) {
                $validator->errors()->add(
                    'tax_regimes',
                    'Debe seleccionar al menos un régimen fiscal para el tipo de persona capturado.'
                );
            }

            if ($personType === 'extranjero' && ! empty($taxRegimes)) {
                $validator->errors()->add(
                    'tax_regimes',
                    'Los proveedores extranjeros no deben capturar regímenes fiscales SAT.'
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
