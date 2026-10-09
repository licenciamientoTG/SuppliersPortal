<?php

namespace App\Http\Requests;

use App\Enum\RequisitionStatus;
use App\Reports\Purchasing\RequisitionPipelineReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Parámetros de RC-01 (pantalla, datos y exportación). */
class RequisitionPipelineReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statuses' => ['nullable', 'array'], 'statuses.*' => [Rule::in(array_column(RequisitionStatus::cases(), 'value'))],
            'pending_approver_id' => ['nullable', 'integer'],
            'older_than_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'cost_center_ids' => ['nullable', 'array'], 'cost_center_ids.*' => ['integer'],
            'amount_from' => ['nullable', 'numeric', 'min:0'],
            'amount_to' => ['nullable', 'numeric', 'min:0', 'gte:amount_from'],
            'company_ids' => ['nullable', 'array'], 'company_ids.*' => ['integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ];
    }

    /** Parámetros con valores por defecto: últimos 90 días y todas menos completadas y canceladas. */
    public function params(): array
    {
        $today = now(config('app.timezone'));
        $ids = fn (string $key) => array_values(array_map('intval', array_filter((array) $this->input($key, []))));
        $statuses = array_values(array_filter((array) $this->input('statuses', [])));
        $amount = fn (string $key) => $this->filled($key) ? round((float) $this->input($key), 2) : null;

        return [
            'statuses' => $statuses ?: self::defaultStatuses(),
            'pending_approver_id' => $this->filled('pending_approver_id') ? (int) $this->input('pending_approver_id') : null,
            'older_than_days' => (int) $this->input('older_than_days', 0),
            'cost_center_ids' => $ids('cost_center_ids'),
            'amount_from' => $amount('amount_from'),
            'amount_to' => $amount('amount_to'),
            'company_ids' => $ids('company_ids'),
            'date_from' => $this->input('date_from') ?: $today->copy()->subDays(90)->toDateString(),
            'date_to' => $this->input('date_to') ?: $today->toDateString(),
        ];
    }

    public static function defaultStatuses(): array
    {
        return array_values(array_diff(array_column(RequisitionStatus::cases(), 'value'), RequisitionPipelineReport::DEFAULT_EXCLUDED));
    }
}
