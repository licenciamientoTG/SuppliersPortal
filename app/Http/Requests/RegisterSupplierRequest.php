<?php

namespace App\Http\Requests;

use App\Enum\PaymentTerm;
use App\Rules\EfosNotListed;
use App\Rules\ValidRfc;
use App\Support\SupplierFiscalCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isForeign = $this->boolean('is_foreign');

        return [
            'is_foreign' => ['nullable', 'boolean'],
            'accepted_prefilled_data' => ['required', 'accepted'],

            'first_name' => [$isForeign ? 'required' : 'nullable', 'string', 'max:100'],
            'last_name' => [$isForeign ? 'required' : 'nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:suppliers,email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],

            'company_name' => [$isForeign ? 'required' : 'nullable', 'string', 'max:255'],
            'rfc' => array_values(array_filter([
                $isForeign ? 'required' : 'nullable',
                'string',
                'max:13',
                'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/',
                $isForeign ? Rule::unique('suppliers', 'rfc') : null,
                $isForeign ? new ValidRfc() : null,
                $isForeign ? new EfosNotListed() : null,
            ])),
            'address' => [$isForeign ? 'required' : 'nullable', 'string', 'max:1000'],
            'postal_code' => [$isForeign ? 'required' : 'nullable', 'string', 'regex:/^\d{5}$/'],
            'csf_upload_token' => [$isForeign ? 'nullable' : 'required', 'string', 'max:100'],

            'supplier_type' => ['required', 'in:product,service,product_service'],
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
            'accepted_prefilled_data.required' => 'Debes confirmar que la informacion es correcta.',
            'accepted_prefilled_data.accepted' => 'Debes confirmar que la informacion es correcta.',
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
            'csf_upload_token.required' => 'Debes cargar y validar tu constancia de situacion fiscal.',
            'phone_number.required' => 'El telefono de la empresa es obligatorio.',
            'phone_number.regex' => 'El telefono de la empresa debe tener exactamente 10 digitos numericos sin espacios ni guiones.',
            'contact_person.required' => 'La persona de contacto es obligatoria.',
            'contact_phone.regex' => 'El telefono de contacto debe tener exactamente 10 digitos numericos sin espacios ni guiones.',
            'supplier_type.required' => 'El tipo de proveedor es obligatorio.',
            'supplier_type.in' => 'Seleccione un tipo de proveedor valido.',
            'economic_activity.required' => 'Debe capturar al menos una actividad economica.',
            'economic_activity.array' => 'Las actividades economicas deben enviarse como una lista valida.',
            'economic_activity.min' => 'Debe capturar al menos una actividad economica.',
            'economic_activity.*.required' => 'Las actividades economicas no pueden estar vacias.',
            'economic_activity.*.max' => 'Cada actividad economica no puede exceder 150 caracteres.',
            'provides_specialized_services.required' => 'Debe indicar si presta servicios especializados.',
            'provides_specialized_services.boolean' => 'Debe seleccionar si o no para servicios especializados.',
            'repse_registration_number.required_if' => 'El numero de registro REPSE es obligatorio para proveedores de servicios especializados.',
            'repse_expiry_date.required_if' => 'La fecha de vencimiento REPSE es obligatoria para proveedores de servicios especializados.',
            'repse_expiry_date.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
            'specialized_services_types.required_if' => 'Debe seleccionar al menos un tipo de servicio especializado.',
            'specialized_services_types.min' => 'Debe seleccionar al menos un tipo de servicio.',
            'otros_descripcion.required_if' => 'Debe especificar que otros servicios ofrece.',
            'default_payment_terms.required' => 'Las condiciones de pago son obligatorias.',
            'default_payment_terms.in' => 'Las condiciones de pago seleccionadas no son validas.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_foreign' => filter_var($this->input('is_foreign', false), FILTER_VALIDATE_BOOLEAN),
        ]);

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
                    $validator->errors()->add('otros_descripcion', 'Debe especificar que otros servicios ofrece.');
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
        });
    }
}
