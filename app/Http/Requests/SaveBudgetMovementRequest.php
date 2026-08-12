<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBudgetMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'movement_type' => ['required', Rule::in(['TRANSFERENCIA', 'AMPLIACION', 'REDUCCION'])],
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2050'],
            'movement_date' => ['required', 'date'],
            'justification' => ['required', 'string', 'min:10', 'max:1000'],
            'total_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
        ];

        $movementType = $this->input('movement_type');

        if ($movementType === 'TRANSFERENCIA') {
            $rules = array_merge($rules, [
                // Origen
                'origin_cost_center_id' => ['required', 'exists:cost_centers,id'],
                'origin_month' => ['required', 'integer', 'min:1', 'max:12'],
                'origin_expense_category_id' => ['required', 'exists:expense_categories,id'],
                'origin_budget_cedula_id' => ['required', 'exists:budget_cedulas,id'],

                // Destino
                'destination_cost_center_id' => ['required', 'exists:cost_centers,id'],
                'destination_month' => ['required', 'integer', 'min:1', 'max:12'],
                'destination_expense_category_id' => ['required', 'exists:expense_categories,id'],
                'destination_budget_cedula_id' => ['required', 'exists:budget_cedulas,id'],
            ]);
        } elseif ($movementType === 'AMPLIACION') {
            $rules = array_merge($rules, [
                'cost_center_id' => ['required', 'exists:cost_centers,id'],
                'month' => ['required', 'integer', 'min:1', 'max:12'],
                'expense_category_id' => ['required', 'exists:expense_categories,id'],
                'budget_cedula_id' => ['required', 'exists:budget_cedulas,id'],
            ]);
        } elseif ($movementType === 'REDUCCION') {
            $rules = array_merge($rules, [
                'cost_center_id' => ['required', 'exists:cost_centers,id'],
                'month' => ['required', 'integer', 'min:1', 'max:12'],
                'expense_category_id' => ['required', 'exists:expense_categories,id'],
                'budget_cedula_id' => ['required', 'exists:budget_cedulas,id'],
            ]);
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->input('movement_type') === 'TRANSFERENCIA') {
                $originCC = $this->input('origin_cost_center_id');
                $destCC = $this->input('destination_cost_center_id');
                $originMonth = $this->input('origin_month');
                $destMonth = $this->input('destination_month');
                $originCat = $this->input('origin_expense_category_id');
                $destCat = $this->input('destination_expense_category_id');
                $originCedula = $this->input('origin_budget_cedula_id');
                $destinationCedula = $this->input('destination_budget_cedula_id');

                if ($originCC == $destCC
                    && $originMonth == $destMonth
                    && $originCat == $destCat
                    && $originCedula == $destinationCedula) {
                    $validator->errors()->add(
                        'destination_cost_center_id',
                        'En una transferencia, al menos uno de estos campos debe ser diferente: centro de costo, mes, cuenta o subcuenta.'
                    );
                }
            }

            $accountSubaccountPairs = match ($this->input('movement_type')) {
                'TRANSFERENCIA' => [
                    'origin_budget_cedula_id' => 'origin_expense_category_id',
                    'destination_budget_cedula_id' => 'destination_expense_category_id',
                ],
                'AMPLIACION', 'REDUCCION' => ['budget_cedula_id' => 'expense_category_id'],
                default => [],
            };

            foreach ($accountSubaccountPairs as $subaccountField => $accountField) {
                if (! $this->filled($subaccountField) || ! $this->filled($accountField)) {
                    continue;
                }

                $belongsToAccount = \App\Models\BudgetCedula::query()
                    ->active()
                    ->notDeleted()
                    ->whereKey($this->input($subaccountField))
                    ->where('expense_category_id', $this->input($accountField))
                    ->exists();

                if (! $belongsToAccount) {
                    $validator->errors()->add($subaccountField, 'La subcuenta seleccionada no pertenece a la cuenta indicada o no está activa.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            // Mensajes generales
            'movement_type.required' => 'Debe seleccionar un tipo de movimiento.',
            'movement_type.in' => 'El tipo de movimiento seleccionado no es válido.',
            'fiscal_year.required' => 'El año fiscal es obligatorio.',
            'fiscal_year.integer' => 'El año fiscal debe ser un número entero.',
            'fiscal_year.min' => 'El año fiscal debe ser mayor o igual a 2020.',
            'fiscal_year.max' => 'El año fiscal debe ser menor o igual a 2050.',
            'movement_date.required' => 'La fecha del movimiento es obligatoria.',
            'movement_date.date' => 'La fecha del movimiento no es válida.',
            'justification.required' => 'La justificación es obligatoria.',
            'justification.min' => 'La justificación debe tener al menos 10 caracteres.',
            'justification.max' => 'La justificación no debe exceder 1000 caracteres.',
            'total_amount.required' => 'El monto total es obligatorio.',
            'total_amount.numeric' => 'El monto total debe ser un número.',
            'total_amount.min' => 'El monto total debe ser mayor a 0.',
            'total_amount.max' => 'El monto total es demasiado grande.',

            // Mensajes para ORIGEN (Transferencias y Reducciones)
            'origin_cost_center_id.required' => 'Debe seleccionar el centro de costo origen.',
            'origin_cost_center_id.exists' => 'El centro de costo origen seleccionado no existe.',
            'origin_month.required' => 'Debe seleccionar el mes origen.',
            'origin_month.integer' => 'El mes origen debe ser un número.',
            'origin_month.min' => 'El mes origen debe ser entre 1 y 12.',
            'origin_month.max' => 'El mes origen debe ser entre 1 y 12.',
            'origin_expense_category_id.required' => 'Debe seleccionar la cuenta origen.',
            'origin_expense_category_id.exists' => 'La cuenta origen seleccionada no existe.',
            'origin_budget_cedula_id.required' => 'Debe seleccionar la subcuenta origen.',
            'origin_budget_cedula_id.exists' => 'La subcuenta origen seleccionada no existe.',

            // Mensajes para DESTINO (Transferencias y Ampliaciones)
            'destination_cost_center_id.required' => 'Debe seleccionar el centro de costo destino.',
            'destination_cost_center_id.exists' => 'El centro de costo destino seleccionado no existe.',
            'destination_cost_center_id.different' => 'El centro de costo destino debe ser diferente al origen.',
            'destination_month.required' => 'Debe seleccionar el mes destino.',
            'destination_month.integer' => 'El mes destino debe ser un número.',
            'destination_month.min' => 'El mes destino debe ser entre 1 y 12.',
            'destination_month.max' => 'El mes destino debe ser entre 1 y 12.',
            'destination_expense_category_id.required' => 'Debe seleccionar la cuenta destino.',
            'destination_expense_category_id.exists' => 'La cuenta destino seleccionada no existe.',
            'destination_budget_cedula_id.required' => 'Debe seleccionar la subcuenta destino.',
            'destination_budget_cedula_id.exists' => 'La subcuenta destino seleccionada no existe.',

            // Mensajes genéricos para Ampliaciones y Reducciones
            'cost_center_id.required' => 'Debe seleccionar el centro de costo.',
            'cost_center_id.exists' => 'El centro de costo seleccionado no existe.',
            'month.required' => 'Debe seleccionar el mes.',
            'month.integer' => 'El mes debe ser un número.',
            'month.min' => 'El mes debe ser entre 1 y 12.',
            'month.max' => 'El mes debe ser entre 1 y 12.',
            'expense_category_id.required' => 'Debe seleccionar la cuenta.',
            'expense_category_id.exists' => 'La cuenta seleccionada no existe.',
            'budget_cedula_id.required' => 'Debe seleccionar la subcuenta a ampliar.',
            'budget_cedula_id.exists' => 'La subcuenta seleccionada no existe.',
        ];
    }

    public function attributes(): array
    {
        return [
            'movement_type' => 'tipo de movimiento',
            'fiscal_year' => 'año fiscal',
            'movement_date' => 'fecha del movimiento',
            'justification' => 'justificación',
            'total_amount' => 'monto total',
            'origin_cost_center_id' => 'centro de costo origen',
            'origin_month' => 'mes origen',
            'origin_expense_category_id' => 'cuenta origen',
            'origin_budget_cedula_id' => 'subcuenta origen',
            'destination_cost_center_id' => 'centro de costo destino',
            'destination_month' => 'mes destino',
            'destination_expense_category_id' => 'cuenta destino',
            'destination_budget_cedula_id' => 'subcuenta destino',
            'cost_center_id' => 'centro de costo',
            'month' => 'mes',
            'expense_category_id' => 'cuenta',
            'budget_cedula_id' => 'subcuenta',
        ];
    }
}
