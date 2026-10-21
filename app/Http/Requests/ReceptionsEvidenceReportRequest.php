<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Parámetros de RR-01 (pantalla, datos y exportación). */
class ReceptionsEvidenceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_ids' => ['nullable', 'array'], 'company_ids.*' => ['integer'],
            'location_ids' => ['nullable', 'array'], 'location_ids.*' => ['integer'],
            'reception_type' => ['nullable', 'array'], 'reception_type.*' => ['string', 'in:BIEN,SERVICIO'],
            'received_by' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'evidence_filter' => ['nullable', 'string', 'in:COMPLETA,INCOMPLETA,TODAS'],
        ];
    }

    public function params(): array
    {
        $ids = fn (string $key) => array_values(array_map('intval', array_filter((array) $this->input($key, []))));
        $strings = fn (string $key) => array_values(array_filter((array) $this->input($key, [])));

        return [
            'company_ids' => $ids('company_ids'),
            'location_ids' => $ids('location_ids'),
            'reception_type' => $strings('reception_type'),
            'received_by' => $this->filled('received_by') ? (int) $this->input('received_by') : null,
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'evidence_filter' => $this->input('evidence_filter', 'TODAS'),
        ];
    }
}
