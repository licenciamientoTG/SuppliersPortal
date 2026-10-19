<?php

namespace App\Http\Requests;

use App\Reports\Purchasing\OpenPurchaseOrderBacklogReport;
use Illuminate\Foundation\Http\FormRequest;

/** Parámetros de RC-03 (pantalla, datos y exportación). */
class OpenPurchaseOrderBacklogReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_ids' => ['nullable', 'array'], 'company_ids.*' => ['integer'],
            'supplier_id' => ['nullable', 'integer'],
            'cost_center_ids' => ['nullable', 'array'], 'cost_center_ids.*' => ['integer'],
            'only_late' => ['nullable', 'boolean'],
            'only_zombie' => ['nullable', 'boolean'],
            'zombie_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'as_of_date' => ['nullable', 'date'],
        ];
    }

    public function params(): array
    {
        $ids = fn (string $key) => array_values(array_map('intval', array_filter((array) $this->input($key, []))));

        return [
            'company_ids' => $ids('company_ids'),
            'supplier_id' => $this->filled('supplier_id') ? (int) $this->input('supplier_id') : null,
            'cost_center_ids' => $ids('cost_center_ids'),
            'only_late' => $this->boolean('only_late'),
            'only_zombie' => $this->boolean('only_zombie'),
            'zombie_days' => $this->filled('zombie_days') ? (int) $this->input('zombie_days') : OpenPurchaseOrderBacklogReport::DEFAULT_ZOMBIE_DAYS,
            'as_of_date' => $this->input('as_of_date') ?: now(config('app.timezone'))->toDateString(),
        ];
    }
}
