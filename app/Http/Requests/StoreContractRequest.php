<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'supplier_id'                   => ['required', 'integer', 'exists:suppliers,id'],
            'company_id'                    => ['required', 'integer', 'exists:companies,id'],
            'start_date'                    => ['required', 'date'],
            'end_date'                      => ['required', 'date', 'after:start_date'],
            'contract_amount'               => ['nullable', 'numeric', 'min:0'],
            'products'                      => ['required', 'array', 'min:1'],
            'products.*.product_service_id' => ['required', 'integer', 'exists:products_services,id', 'distinct'],
            'products.*.unit_price'         => ['required', 'numeric', 'min:0.0001'],
            'products.*.currency_code'      => ['required', 'string', 'size:3'],
            'products.*.unit_of_measure'    => ['required', 'string', 'max:50'],
            'products.*.notes'              => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after'                         => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'products.required'                      => 'El contrato debe tener al menos un producto.',
            'products.*.product_service_id.distinct' => 'No puedes agregar el mismo producto dos veces.',
        ];
    }
}
