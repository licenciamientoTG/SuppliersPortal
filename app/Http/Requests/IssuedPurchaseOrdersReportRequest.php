<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Parámetros de RC-02 (pantalla, datos y exportación). */
class IssuedPurchaseOrdersReportRequest extends FormRequest
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
            'buyer_id' => ['nullable', 'integer'],
            'origin' => ['nullable', 'array'], 'origin.*' => ['string', 'in:REQUISICION,CONTRATO,DIRECTA'],
            'status' => ['nullable', 'array'], 'status.*' => ['string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    public function params(): array
    {
        $ids = fn (string $key) => array_values(array_map('intval', array_filter((array) $this->input($key, []))));
        $strings = fn (string $key) => array_values(array_filter((array) $this->input($key, [])));

        return [
            'company_ids' => $ids('company_ids'),
            'supplier_id' => $this->filled('supplier_id') ? (int) $this->input('supplier_id') : null,
            'buyer_id' => $this->filled('buyer_id') ? (int) $this->input('buyer_id') : null,
            'origin' => $strings('origin'),
            'status' => $strings('status'),
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
        ];
    }
}
