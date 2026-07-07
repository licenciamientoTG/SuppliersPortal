<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ReceivingLocation;

class SaveReceivingLocationRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud
     * 
     * @return bool
     */
    public function authorize(): bool
    {
        // Si es una actualización, verificar que el usuario tenga permiso
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            return $this->user()->can('update', $this->route('receiving_location'));
        }
        
        // Si es creación, verificar permiso de crear
        return $this->user()->can('create', ReceivingLocation::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'company_id' => [
                'required',
                'integer',
                'exists:companies,id',
            ],
            // Reglas comunes para creación y actualización
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-ZáéíóúñÑüÜ0-9\s\.,-]+$/',
            ],
            'type' => [
                'required',
                'string',
                Rule::in(ReceivingLocation::TYPES),
            ],
            'address' => [
                'nullable',
                'string',
                'max:255',
            ],
            'city' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-ZáéíóúñÑüÜ\s-]+$/',
            ],
            'state' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[a-zA-ZáéíóúñÑüÜ\s-]+$/',
            ],
            'country' => [
                'nullable',
                'string',
                'max:50',
            ],
            'postal_code' => [
                'nullable',
                'string',
                'max:10',
                'regex:/^[0-9]{4,5}$/',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9\+\-\s\(\)]{10,20}$/',
            ],
            'email' => [
                'nullable',
                'email',
                'max:100',
            ],
            'manager_name' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-ZáéíóúñÑüÜ\s]+$/',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'portal_blocked' => [
                'sometimes',
                'boolean',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];

        // Reglas específicas para creación (POST)
        if ($this->isMethod('POST')) {
            $rules['code'] = [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9\-_]{3,20}$/',
                Rule::unique('receiving_locations', 'code'),
            ];
        }

        // Reglas específicas para actualización (PUT/PATCH)
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $receivingLocation = $this->route('receiving_location');
            
            $rules['code'] = [
                'sometimes',
                'string',
                'max:20',
                'regex:/^[A-Z0-9\-_]{3,20}$/',
                Rule::unique('receiving_locations', 'code')->ignore($receivingLocation?->id),
            ];
        }

        return $rules;
    }

    /**
     * Prepara los datos para la validación
     * Se ejecuta antes de la validación
     */
    protected function prepareForValidation(): void
    {
        // Valores por defecto para creación
        if ($this->isMethod('POST')) {
            $this->merge([
                'is_active' => $this->is_active ?? true,
                'portal_blocked' => $this->portal_blocked ?? false,
                'country' => $this->country ?? 'México',
            ]);
        }

        // Limpiar espacios extras en campos de texto
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(trim($this->code)),
            ]);
        }

        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->name),
            ]);
        }

        // Formatear teléfono si viene
        if ($this->has('phone') && !empty($this->phone)) {
            $this->merge([
                'phone' => preg_replace('/[^0-9\+\-]/', '', $this->phone),
            ]);
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Código
            'code.required' => 'El código de ubicación es obligatorio.',
            'code.string' => 'El código debe ser una cadena de texto.',
            'code.max' => 'El código no puede tener más de :max caracteres.',
            'code.regex' => 'El código solo puede contener letras mayúsculas, números, guiones y guiones bajos.',
            'code.unique' => 'Este código de ubicación ya está registrado.',

            // Nombre
            'name.required' => 'El nombre de la ubicación es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede tener más de :max caracteres.',
            'name.regex' => 'El nombre solo puede contener letras, números, espacios, puntos y comas.',

            // Tipo
            'type.required' => 'El tipo de ubicación es obligatorio.',
            'type.string' => 'El tipo debe ser una cadena de texto.',
            'type.in' => 'El tipo de ubicación seleccionado no es válido.',

            // Ciudad
            'city.regex' => 'La ciudad solo puede contener letras y espacios.',

            // Estado
            'state.regex' => 'El estado solo puede contener letras y espacios.',

            // Código postal
            'postal_code.regex' => 'El código postal debe tener entre 4 y 5 dígitos.',

            // Teléfono
            'phone.regex' => 'El teléfono debe tener entre 10 y 20 caracteres y puede incluir números, +, -, ( y ).',
            'phone.max' => 'El teléfono no puede tener más de :max caracteres.',

            // Email
            'email.email' => 'El correo electrónico debe ser válido.',
            'email.max' => 'El correo no puede tener más de :max caracteres.',

            // Responsable
            'manager_name.regex' => 'El nombre del responsable solo puede contener letras y espacios.',
            'manager_name.max' => 'El nombre del responsable no puede tener más de :max caracteres.',

            // Notas
            'notes.max' => 'Las notas no pueden tener más de :max caracteres.',

            // Booleanos
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
            'portal_blocked.boolean' => 'El estado del portal debe ser verdadero o falso.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'código',
            'name' => 'nombre',
            'type' => 'tipo',
            'address' => 'dirección',
            'city' => 'ciudad',
            'state' => 'estado',
            'country' => 'país',
            'postal_code' => 'código postal',
            'phone' => 'teléfono',
            'email' => 'correo electrónico',
            'manager_name' => 'responsable',
            'is_active' => 'activo',
            'portal_blocked' => 'portal bloqueado',
            'notes' => 'notas',
        ];
    }

    /**
     * Handle a passed validation attempt.
     * Se ejecuta después de una validación exitosa
     */
    protected function passedValidation(): void
    {
        // Si es creación, establecer valores por defecto si no vienen
        if ($this->isMethod('POST')) {
            $this->replace($this->merge([
                'country' => $this->country ?? 'México',
                'is_active' => $this->is_active ?? true,
                'portal_blocked' => $this->portal_blocked ?? false,
            ])->all());
        }
    }

    /**
     * Determine if the request has valid non-empty data for creation.
     * Útil para verificar si hay datos para crear antes de procesar
     *
     * @return bool
     */
    public function hasValidCreationData(): bool
    {
        return $this->isMethod('POST') && 
               $this->filled(['code', 'name', 'type']);
    }

    /**
     * Determine if the request has valid non-empty data for update.
     * Útil para verificar si hay datos para actualizar antes de procesar
     *
     * @return bool
     */
    public function hasValidUpdateData(): bool
    {
        return ($this->isMethod('PUT') || $this->isMethod('PATCH')) && 
               $this->anyFilled([
                   'code', 'name', 'type', 'address', 'city', 
                   'state', 'country', 'postal_code', 'phone', 
                   'email', 'manager_name', 'notes'
               ]);
    }

    /**
     * Get the validated data from the request with defaults applied.
     * Versión extendida de validated() que incluye los mergeados
     *
     * @return array<string, mixed>
     */
    public function getValidatedWithDefaults(): array
    {
        $validated = $this->validated();

        // Asegurar booleanos para campos que pueden no venir en la petición
        if ($this->isMethod('POST')) {
            $validated['is_active'] = $validated['is_active'] ?? true;
            $validated['portal_blocked'] = $validated['portal_blocked'] ?? false;
        }

        return $validated;
    }
}
