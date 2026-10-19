<?php

namespace App\Http\Requests;

use App\Reports\Budget\BudgetVsActualReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Parámetros de RP-01 (pantalla, datos y detalle por monto). */
class BudgetVsActualReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'fiscal_year' => ['nullable', 'integer', 'between:2020,2100'],
            'period_month' => ['nullable', 'integer', 'between:1,12'],
            'scope' => ['nullable', 'in:MES,ACU,RANGO,CORTE'],
            'date_from' => ['required_if:scope,RANGO', 'date'],
            'date_to' => ['required_if:scope,RANGO', 'date', 'after_or_equal:date_from'],
            'as_of_date' => ['required_if:scope,CORTE', 'date'],
            'company_ids' => ['nullable', 'array'], 'company_ids.*' => ['integer'],
            'cost_center_ids' => ['nullable', 'array'], 'cost_center_ids.*' => ['integer'],
            'expense_category_ids' => ['nullable', 'array'], 'expense_category_ids.*' => ['integer'],
            'responsible_user_id' => ['nullable', 'integer'],
            'include_cancelled_po' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ];

        if ($this->routeIs('budget-vs-actual-reports.detail')) {
            $rules += [
                'cost_center_id' => ['required', 'integer'],
                'expense_category_id' => ['required', 'integer'],
                'budget_cedula_id' => ['nullable', 'integer'],
                'bucket' => ['required', Rule::in(BudgetVsActualReport::BUCKETS)],
            ];
        }

        return $rules;
    }

    /** El rango no puede cruzar ejercicios: BudgetPositionService arma la posición de un solo fiscal_year. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('scope') !== 'RANGO' || ! $this->filled('date_from') || ! $this->filled('date_to')) {
                return;
            }
            if (Carbon::parse($this->input('date_from'))->year !== Carbon::parse($this->input('date_to'))->year) {
                $validator->errors()->add('date_to', 'El rango de fechas debe estar dentro de un mismo ejercicio fiscal.');
            }
        });

        $validator->after(function (Validator $validator) {
            if ($this->input('scope') !== 'CORTE' || ! $this->filled('as_of_date')) {
                return;
            }
            if (Carbon::parse($this->input('as_of_date'))->startOfDay()->gt(now(config('app.timezone'))->startOfDay())) {
                $validator->errors()->add('as_of_date', 'La fecha de corte no puede ser futura.');
            }
        });
    }

    /** Parámetros con valores por defecto: ejercicio y mes en curso, vista mensual. */
    public function params(): array
    {
        $now = now(config('app.timezone'));
        $ids = fn (string $key) => array_values(array_map('intval', array_filter((array) $this->input($key, []))));
        $scope = $this->input('scope', 'MES');

        if ($scope === 'RANGO') {
            $from = Carbon::parse($this->input('date_from'));
            $to = Carbon::parse($this->input('date_to'));
            $fiscalYear = $from->year;
            $periodMonth = $to->month;
            $periodMonthFrom = $from->month;
        } else {
            $fiscalYear = (int) $this->input('fiscal_year', $now->year);
            $periodMonth = (int) $this->input('period_month', $now->month);
            $periodMonthFrom = null;
        }

        return [
            'fiscal_year' => $fiscalYear,
            'period_month' => $periodMonth,
            'period_month_from' => $periodMonthFrom,
            'scope' => $scope,
            'date_from' => $scope === 'RANGO' ? $from->toDateString() : null,
            'date_to' => $scope === 'RANGO' ? $to->toDateString() : null,
            'as_of_date' => $scope === 'CORTE' ? Carbon::parse($this->input('as_of_date'))->toDateString() : null,
            'company_ids' => $ids('company_ids'),
            'cost_center_ids' => $ids('cost_center_ids'),
            'expense_category_ids' => $ids('expense_category_ids'),
            'responsible_user_id' => $this->filled('responsible_user_id') ? (int) $this->input('responsible_user_id') : null,
            'include_cancelled_po' => $this->boolean('include_cancelled_po'),
        ];
    }
}
