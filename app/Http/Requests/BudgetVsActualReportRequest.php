<?php

namespace App\Http\Requests;

use App\Reports\Budget\BudgetVsActualReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'scope' => ['nullable', 'in:MES,ACU'],
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

    /** Parámetros con valores por defecto: ejercicio y mes en curso, vista mensual. */
    public function params(): array
    {
        $now = now(config('app.timezone'));
        $ids = fn (string $key) => array_values(array_map('intval', array_filter((array) $this->input($key, []))));

        return [
            'fiscal_year' => (int) $this->input('fiscal_year', $now->year),
            'period_month' => (int) $this->input('period_month', $now->month),
            'scope' => $this->input('scope', 'MES'),
            'company_ids' => $ids('company_ids'),
            'cost_center_ids' => $ids('cost_center_ids'),
            'expense_category_ids' => $ids('expense_category_ids'),
            'responsible_user_id' => $this->filled('responsible_user_id') ? (int) $this->input('responsible_user_id') : null,
            'include_cancelled_po' => $this->boolean('include_cancelled_po'),
        ];
    }
}
