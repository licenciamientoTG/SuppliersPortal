<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\EfosNotListed;
use App\Rules\ValidRfc;
use App\Enum\PaymentTerm;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;

class RegisterSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // público
    }

    public function rules(): array
    {
        return [
            // Paso 1 - Cuenta
            'first_name' => ['required','string','max:100'],
            'last_name'  => ['required','string','max:100'],
            'email'      => ['required','email','max:255','unique:users,email'],
            'password'   => ['required','confirmed','min:8'],

            // Paso 2 - Empresa
            'company_name'  => ['required','string','max:255'],
            'rfc'           => ['required','string','max:13','regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/','unique:suppliers,rfc', new ValidRfc, new EfosNotListed],
            'supplier_type' => ['required','in:product,service,product_service'],
            'tax_regime'    => ['required','in:individual,corporation,resico'],
            'address'       => ['required','string','max:1000'],
            'phone_number'  => ['required','string','regex:/^\d{10}$/'],
            'contact_person'=> ['required','string','max:255'],
            'contact_phone' => ['nullable','string','regex:/^\d{10}$/'],

            // Nuevas validaciones REPSE
            'provides_specialized_services' => ['required', 'boolean'],
            'repse_registration_number'     => ['required_if:provides_specialized_services,1', 'nullable', 'string', 'max:50'],
            'repse_expiry_date'            => ['required_if:provides_specialized_services,1', 'nullable', 'date', 'after:today'],
            'specialized_services_types'   => ['required_if:provides_specialized_services,1', 'nullable', 'array', 'min:1'],
            'specialized_services_types.*' => ['string', Rule::in([
                'limpieza',
                'vigilancia',
                'mantenimiento',
                'alimentacion',
                'contabilidad',
                'sistemas',
                'otros'
            ])],
            'otros_descripcion' => ['nullable', 'string', 'max:255', 'required_if:specialized_services_types.*,otros'],
            'economic_activity'     => ['required', 'string', 'max:150'],
            'default_payment_terms' => ['required', Rule::in(array_column(PaymentTerm::cases(), 'value'))],
        ];
    }

    public function messages(): array
    {
        return [
            // Mensajes para campos básicos
            'first_name.required' => 'El nombre es obligatorio.',
            'last_name.required'  => 'Los apellidos son obligatorios.',
            'email.required'      => 'El correo electrónico es obligatorio.',
            'email.email'         => 'Debe ser un correo electrónico válido.',
            'email.unique'        => 'Este correo ya está registrado.',
            'password.required'   => 'La contraseña es obligatoria.',
            'password.min'        => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed'  => 'Las contraseñas no coinciden.',

            // Mensajes para datos de empresa
            'company_name.required' => 'La razón social es obligatoria.',
            'rfc.required'         => 'El RFC es obligatorio.',
            'rfc.regex'           => 'El formato del RFC no es válido.',
            'address.required'     => 'La dirección es obligatoria.',
            'phone_number.required'=> 'El teléfono de la empresa es obligatorio.',
            'phone_number.regex'  => 'El teléfono de la empresa debe tener exactamente 10 dígitos numéricos (sin espacios ni guiones).',
            'contact_person.required' => 'La persona de contacto es obligatoria.',
            'contact_phone.regex' => 'El teléfono de contacto debe tener exactamente 10 dígitos numéricos (sin espacios ni guiones).',
            'economic_activity.required' => 'La actividad económica es obligatoria.',
            'supplier_type.required' => 'El tipo de proveedor es obligatorio.',
            'supplier_type.in'    => 'Seleccione un tipo de proveedor válido.',
            'tax_regime.required' => 'El régimen fiscal es obligatorio.',
            'tax_regime.in'       => 'Seleccione un régimen fiscal válido.',

            // Mensajes específicos para REPSE
            'provides_specialized_services.required' => 'Debe indicar si presta servicios especializados.',
            'provides_specialized_services.boolean'  => 'Debe seleccionar sí o no para servicios especializados.',

            'repse_registration_number.required_if' => 'El número de registro REPSE es obligatorio para proveedores de servicios especializados.',
            'repse_registration_number.max'         => 'El número REPSE no puede exceder 50 caracteres.',

            'repse_expiry_date.required_if' => 'La fecha de vencimiento REPSE es obligatoria para proveedores de servicios especializados.',
            'repse_expiry_date.date'        => 'Debe ser una fecha válida.',
            'repse_expiry_date.after'       => 'La fecha de vencimiento debe ser posterior a hoy.',

            'specialized_services_types.required_if' => 'Debe seleccionar al menos un tipo de servicio especializado.',
            'specialized_services_types.array'       => 'Los tipos de servicios deben ser una lista válida.',
            'specialized_services_types.min'         => 'Debe seleccionar al menos un tipo de servicio.',
            'specialized_services_types.*.in'        => 'Uno o más tipos de servicios seleccionados no son válidos.',

            'otros_descripcion.required_if' => 'Debe especificar qué otros servicios ofrece.',
            'otros_descripcion.max'         => 'La descripción de otros servicios no puede exceder 255 caracteres.',
            'economic_activity.max'          => 'La actividad económica no puede exceder 150 caracteres.',
            'default_payment_terms.required' => 'Las condiciones de pago son obligatorias.',
            'default_payment_terms.in'       => 'Las condiciones de pago seleccionadas no son válidas.',
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name'                    => 'nombre',
            'last_name'                     => 'apellidos',
            'email'                         => 'correo electrónico',
            'password'                      => 'contraseña',
            'company_name'                  => 'razón social',
            'rfc'                           => 'RFC',
            'address'                       => 'dirección',
            'phone_number'                  => 'teléfono de empresa',
            'contact_person'                => 'persona de contacto',
            'contact_phone'                 => 'teléfono de contacto',
            'supplier_type'                 => 'tipo de proveedor',
            'tax_regime'                    => 'régimen fiscal',
            'provides_specialized_services' => 'servicios especializados',
            'repse_registration_number'     => 'número de registro REPSE',
            'repse_expiry_date'             => 'fecha de vencimiento REPSE',
            'specialized_services_types'    => 'tipos de servicios especializados',
            'otros_descripcion'             => 'descripción de otros servicios',
            'economic_activity'             => 'actividad económica',
            'default_payment_terms'         => 'condiciones de pago',
        ];
    }

    /**
     * Preparar datos para validación
     */
    protected function prepareForValidation(): void
    {
        // Convertir provides_specialized_services a boolean
        if ($this->has('provides_specialized_services')) {
            $this->merge([
                'provides_specialized_services' => filter_var(
                    $this->provides_specialized_services,
                    FILTER_VALIDATE_BOOLEAN
                )
            ]);
        }

        // Limpiar y formatear RFC
        if ($this->has('rfc')) {
            $this->merge([
                'rfc' => strtoupper(trim($this->rfc))
            ]);
        }

        // Limpiar números de teléfono
        if ($this->has('phone_number')) {
            $this->merge([
                'phone_number' => preg_replace('/\D/', '', $this->phone_number)
            ]);
        }

        if ($this->has('contact_phone') && $this->contact_phone) {
            $this->merge([
                'contact_phone' => preg_replace('/\D/', '', $this->contact_phone)
            ]);
        }

        if ($this->has('economic_activity')) {
            $this->merge([
                // recorta y colapsa espacios múltiples
                'economic_activity' => trim(preg_replace('/\s+/', ' ', (string) $this->economic_activity)),
            ]);
        }
    }

    /**
     * Validación adicional personalizada
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que si selecciona "otros", proporcione descripción
            if ($this->provides_specialized_services) {
                $services = $this->specialized_services_types ?? [];

                if (in_array('otros', $services) && empty($this->otros_descripcion)) {
                    $validator->errors()->add(
                        'otros_descripcion',
                        'Debe especificar qué otros servicios ofrece.'
                    );
                }
            }

            // Validar formato del número REPSE (si se proporciona)
            if ($this->repse_registration_number && $this->provides_specialized_services) {
                $number = strtoupper(trim($this->repse_registration_number));

                // Verificar que tenga formato válido (flexible)
                if (!preg_match('/^REPSE-?\w+$/i', $number)) {
                    $validator->errors()->add(
                        'repse_registration_number',
                        'El formato del número REPSE no es válido. Debe comenzar con "REPSE-".'
                    );
                }
            }

            // Advertir si la fecha REPSE vence pronto (no error, solo warning)
            if ($this->repse_expiry_date && $this->provides_specialized_services) {
                $expiryDate = \Carbon\Carbon::parse($this->repse_expiry_date);
                $threeMonthsFromNow = now()->addMonths(3);

                if ($expiryDate <= $threeMonthsFromNow) {
                    // Esto podría ser un mensaje de advertencia en lugar de error
                    // Puedes manejarlo en el frontend o agregarlo como información
                }
            }
        });
    }

    /**
     * Asegura que el usuario regrese al paso donde se detectó el primer error.
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = redirect()
            ->to($this->getRedirectUrl())
            ->withErrors($validator, $this->errorBag)
            ->withInput()
            ->with('supplier_registration_step', $this->resolveStepFromErrors($validator->errors()->keys()));

        throw new HttpResponseException($response);
    }

    /**
     * Determina el primer paso que contiene errores.
     */
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
                'tax_regime',
                'economic_activity',
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
