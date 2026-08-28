<?php

namespace App\Http\Requests;

use App\Models\Subaccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveProductServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Identificación
            'technical_description' => 'nullable|string|max:5000',
            'short_name' => 'nullable|string|max:100',
            'sat_product_code' => ['nullable', 'string', 'regex:/^\\d{8}$/'],
            'product_type' => 'required|in:PRODUCTO,SERVICIO',

            // Clasificación
            'budget_cedula_ids' => 'nullable|array',
            'budget_cedula_ids.*' => 'integer|exists:budget_cedulas,id',
            'department_subaccount_assignments' => 'nullable|array',
            'department_subaccount_assignments.*' => 'array',
            'department_subaccount_assignments.*.*' => 'integer|exists:departments,id',
            'is_inventoriable' => 'boolean',

            // Organización

            // Especificaciones técnicas
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'unit_of_measure' => 'required|string|max:30',
            'specifications' => 'nullable|json',

            // Información comercial
            'estimated_price' => 'required|numeric|min:0|max:9999999999.99',
            'currency_code' => 'nullable|string|size:3|in:MXN,USD,EUR',
            'default_vendor_id' => 'nullable|exists:suppliers,id',
            'minimum_quantity' => 'nullable|numeric|min:0.001|max:9999999.999',
            'maximum_quantity' => 'nullable|numeric|min:0.001|max:9999999.999|gte:minimum_quantity',
            'lead_time_days' => 'nullable|integer|min:1|max:365',

            // Observaciones
            'observations' => 'nullable|string|max:2000',
            'internal_notes' => 'nullable|string|max:2000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $budgetCedulaIds = collect($this->input('budget_cedula_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if ($budgetCedulaIds->count() <= 1) {
                return;
            }

            $assignments = collect($this->input('department_subaccount_assignments', []));
            $seenDepartments = [];
            $subaccountsByCedula = Subaccount::query()
                ->active()
                ->whereIn('legacy_budget_cedula_id', $budgetCedulaIds)
                ->get(['id', 'legacy_budget_cedula_id'])
                ->keyBy('legacy_budget_cedula_id');

            $unexpectedCedulaIds = $assignments->keys()
                ->map(fn ($id) => (int) $id)
                ->diff($budgetCedulaIds);

            if ($unexpectedCedulaIds->isNotEmpty()) {
                $validator->errors()->add(
                    'department_subaccount_assignments',
                    'Solo puedes asignar departamentos a las subcuentas seleccionadas.'
                );
            }

            foreach ($budgetCedulaIds as $budgetCedulaId) {
                $departmentIds = collect($assignments->get($budgetCedulaId, []))
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();

                if ($departmentIds->isEmpty()) {
                    $validator->errors()->add(
                        "department_subaccount_assignments.{$budgetCedulaId}",
                        'Asigna al menos un departamento para cada subcuenta seleccionada.'
                    );
                }

                foreach ($departmentIds as $departmentId) {
                    if (isset($seenDepartments[$departmentId])) {
                        $validator->errors()->add(
                            'department_subaccount_assignments',
                            'Un departamento solo puede asignarse a una subcuenta por producto.'
                        );
                    }
                    $seenDepartments[$departmentId] = true;

                    $subaccount = $subaccountsByCedula->get($budgetCedulaId);
                    $hasBudgetProfileAccess = $subaccount && $subaccount->budgetProfiles()
                        ->active()
                        ->where(function ($query) use ($departmentId) {
                            $query->whereHas('department', fn ($departmentQuery) => $departmentQuery->whereKey($departmentId)->where('is_active', true))
                                ->orWhereHas('departments', fn ($departmentQuery) => $departmentQuery->whereKey($departmentId)->where('is_active', true));
                        })
                        ->exists();

                    if (! $hasBudgetProfileAccess) {
                        $validator->errors()->add(
                            "department_subaccount_assignments.{$budgetCedulaId}",
                            'El departamento seleccionado no tiene esta subcuenta en ninguno de sus perfiles presupuestales activos.'
                        );
                    }
                }
            }

            if ($subaccountsByCedula->count() !== $budgetCedulaIds->count()) {
                $validator->errors()->add('budget_cedula_ids', 'Una subcuenta seleccionada no tiene una equivalencia de subcuenta configurada.');
            }
        });
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'technical_description' => 'descripción técnica',
            'short_name' => 'nombre corto',
            'sat_product_code' => 'código SAT',
            'product_type' => 'tipo de producto',
            'budget_cedula_ids' => 'subcuentas',
            'budget_cedula_ids.*' => 'subcuenta',
            'department_subaccount_assignments' => 'asignación por departamento',
            'is_inventoriable' => 'inventariable',
            'brand' => 'marca',
            'model' => 'modelo',
            'unit_of_measure' => 'unidad de medida',
            'specifications' => 'especificaciones técnicas',
            'estimated_price' => 'precio estimado',
            'currency_code' => 'moneda',
            'default_vendor_id' => 'proveedor sugerido',
            'minimum_quantity' => 'cantidad mínima',
            'maximum_quantity' => 'cantidad máxima',
            'lead_time_days' => 'días de entrega',
            'observations' => 'observaciones',
            'internal_notes' => 'notas internas',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'technical_description.min' => 'La descripción técnica debe tener al menos 20 caracteres.',
            'technical_description.required' => 'La descripción técnica es obligatoria.',
            'sat_product_code.regex' => 'El código SAT debe contener exactamente 8 dígitos.',
            'unit_of_measure.required' => 'La unidad de medida es obligatoria.',
            'product_type.required' => 'Debe especificar si es PRODUCTO o SERVICIO.',
            'product_type.in' => 'El tipo debe ser PRODUCTO o SERVICIO.',
            'maximum_quantity.gte' => 'La cantidad máxima debe ser mayor o igual a la cantidad mínima.',
            'lead_time_days.max' => 'El tiempo de entrega no puede ser mayor a 365 días.',
            'specifications.json' => 'Las especificaciones técnicas deben ser un JSON válido.',
        ];
    }
}
