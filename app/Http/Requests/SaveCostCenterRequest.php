<?php

namespace App\Http\Requests;

use App\Enum\PurchaseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Models\User;

class SaveCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:500'],
            'purchase_type' => ['required', new Enum(PurchaseType::class)],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'responsible_user_id' => ['required', 'integer', 'exists:users,id'],
            'cost_center_type' => ['required', 'in:STANDARD,DISTRIBUTION'],
            'destinations' => ['nullable', 'array'],
            'destinations.*.target_cost_center_id' => ['required_if:cost_center_type,DISTRIBUTION', 'integer', 'exists:cost_centers,id'],
            'destinations.*.percentage' => ['required_if:cost_center_type,DISTRIBUTION', 'numeric', 'gt:0', 'lte:100'],
            'budget_type' => ['required', 'string', 'in:ANNUAL,FREE_CONSUMPTION'],
            'global_amount' => [
                'nullable',
                'required_if:budget_type,FREE_CONSUMPTION',
                'numeric',
                'min:0.01',
                'max:999999999.99',
            ],
            'validity_date' => [
                'nullable',
                'required_if:budget_type,FREE_CONSUMPTION',
                'date',
                'after_or_equal:today',
            ],
            'free_consumption_justification' => [
                'nullable',
                'required_if:budget_type,FREE_CONSUMPTION',
                'string',
                'min:10',
                'max:1000',
            ],
            'status' => ['required', 'string', 'in:ACTIVO,INACTIVO'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'codigo',
            'name' => 'nombre',
            'description' => 'descripcion',
            'purchase_type' => 'tipo de compra',
            'category_id' => 'categoria',
            'company_id' => 'empresa',
            'responsible_user_id' => 'responsable',
            'cost_center_type' => 'tipo de centro',
            'destinations' => 'distribución',
            'budget_type' => 'tipo de presupuesto',
            'global_amount' => 'monto global',
            'validity_date' => 'fecha de vigencia',
            'free_consumption_justification' => 'justificacion',
            'status' => 'estado',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El codigo es requerido.',
            'code.max' => 'El codigo no debe exceder 50 caracteres.',
            'name.required' => 'El nombre del centro de costo es requerido.',
            'name.max' => 'El nombre no debe exceder 200 caracteres.',
            'purchase_type.required' => 'El tipo de compra es obligatorio.',
            'global_amount.required_if' => 'El monto global es requerido para centros de consumo libre.',
            'global_amount.min' => 'El monto global debe ser mayor a 0.',
            'global_amount.max' => 'El monto global no puede ser mayor a 999,999,999.99.',
            'free_consumption_justification.required_if' => 'La justificacion es requerida para centros de consumo libre.',
            'free_consumption_justification.min' => 'La justificacion debe tener al menos 10 caracteres.',
            'validity_date.required_if' => 'La fecha de vigencia es obligatoria para centros de consumo libre.',
            'validity_date.after_or_equal' => 'La fecha de vigencia debe ser igual o posterior a la fecha actual.',
            'validity_date.date' => 'La fecha de vigencia debe ser una fecha valida.',
            'budget_type.required' => 'El tipo de presupuesto es obligatorio.',
            'budget_type.in' => 'El tipo de presupuesto debe ser ANNUAL o FREE_CONSUMPTION.',
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado debe ser ACTIVO o INACTIVO.',
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $userId = $this->integer('responsible_user_id');
            if ($userId && ! User::query()->whereKey($userId)->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->where('name', 'authorizer'))
                ->whereHas('roles', fn ($query) => $query->where('name', 'department_head'))
                ->exists()) {
                $validator->errors()->add('responsible_user_id', 'El responsable debe estar activo y tener los roles autorizador y jefe de departamento.');
            }
        }];
    }
}
