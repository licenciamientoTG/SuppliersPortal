<?php

namespace App\Http\Requests;

use App\Reports\Budget\BudgetAlertsReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Parámetros de RP-03 (pantalla, datos y exportación). */
class BudgetAlertsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fiscal_year' => ['nullable', 'integer', 'between:2020,2100'],
            'threshold' => ['nullable', 'integer', Rule::in(BudgetAlertsReport::THRESHOLDS)],
            'months' => ['nullable', 'array'], 'months.*' => ['integer', 'between:1,12'],
            'company_ids' => ['nullable', 'array'], 'company_ids.*' => ['integer'],
            'cost_center_ids' => ['nullable', 'array'], 'cost_center_ids.*' => ['integer'],
            'responsible_user_id' => ['nullable', 'integer'],
            'exceptions_from' => ['nullable', 'date'],
            'exceptions_to' => ['nullable', 'date', 'after_or_equal:exceptions_from'],
            'exception_status' => ['nullable', 'in:PENDING,APPROVED,REJECTED'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ];
    }

    /** Por omisión: ejercicio en curso, umbral 80 % y excepciones de todo el ejercicio. */
    public function params(): array
    {
        $year = (int) $this->input('fiscal_year', now(config('app.timezone'))->year);
        $ids = fn (string $key) => array_values(array_map('intval', array_filter((array) $this->input($key, []))));

        return [
            'fiscal_year' => $year,
            'threshold' => (int) $this->input('threshold', 80),
            'months' => $ids('months'),
            'company_ids' => $ids('company_ids'),
            'cost_center_ids' => $ids('cost_center_ids'),
            'responsible_user_id' => $this->filled('responsible_user_id') ? (int) $this->input('responsible_user_id') : null,
            'exceptions_from' => $this->input('exceptions_from') ?: "{$year}-01-01",
            'exceptions_to' => $this->input('exceptions_to') ?: "{$year}-12-31",
            'exception_status' => $this->input('exception_status') ?: null,
        ];
    }
}
