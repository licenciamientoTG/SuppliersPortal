<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Parámetros de RF-01 (pantalla, datos y exportación). */
class CfdiValidationLogReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'result' => ['nullable', 'string', 'in:ACEPTADO,RECHAZADO'],
            'rejection_code' => ['nullable', 'array'], 'rejection_code.*' => ['string'],
            'supplier_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    public function params(): array
    {
        $strings = fn (string $key) => array_values(array_filter((array) $this->input($key, [])));

        return [
            'result' => $this->input('result'),
            'rejection_code' => $strings('rejection_code'),
            'supplier_id' => $this->filled('supplier_id') ? (int) $this->input('supplier_id') : null,
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
        ];
    }
}
