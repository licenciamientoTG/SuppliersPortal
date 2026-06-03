<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveProductServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Identificación
            'technical_description' => 'required|string|min:20|max:5000',
            'short_name' => 'nullable|string|max:100',
            'product_type' => 'required|in:PRODUCTO,SERVICIO',
            
            // Clasificación
            'subcategory' => 'nullable|string|max:100',
            
            // Organización
            'company_id' => 'required|exists:companies,id',
            'cost_center_id' => 'required|exists:cost_centers,id',
            
            // Especificaciones técnicas
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'unit_of_measure' => 'required|string|max:30',
            'specifications' => 'nullable|json',
            
            // Información comercial
            'estimated_price' => 'required|numeric|min:0|max:9999999999.99',
            'currency_code' => 'nullable|string|size:3|in:MXN,USD,EUR',
            'default_vendor_id' => 'nullable|exists:suppliers,id',
            'minimum_quantity' => 'nullable|numeric|min:0.001|max:9999999.999',
            'maximum_quantity' => 'nullable|numeric|min:0.001|max:9999999.999|gte:minimum_quantity',
            'lead_time_days' => 'nullable|integer|min:1|max:365',
            
            // Estructura contable
            'account_major' => 'nullable|string|max:50',
            'account_sub' => 'nullable|string|max:50',
            'account_subsub' => 'nullable|string|max:50',
            
            // Observaciones
            'observations' => 'nullable|string|max:2000',
            'internal_notes' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'technical_description' => 'descripción técnica',
            'short_name' => 'nombre corto',
            'product_type' => 'tipo de producto',
            'subcategory' => 'subcategoría',
            'company_id' => 'compañía',
            'cost_center_id' => 'centro de costo',
            'brand' => 'marca',
            'model' => 'modelo',
            'unit_of_measure' => 'unidad de medida',
            'specifications' => 'especificaciones técnicas',
            'estimated_price' => 'precio estimado',
            'currency_code' => 'moneda',
            'default_vendor_id' => 'proveedor sugerido',
            'minimum_quantity' => 'cantidad mínima',
            'maximum_quantity' => 'cantidad máxima',
            'lead_time_days' => 'días de entrega',
            'account_major' => 'cuenta mayor',
            'account_sub' => 'subcuenta',
            'account_subsub' => 'subsubcuenta',
            'observations' => 'observaciones',
            'internal_notes' => 'notas internas',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'technical_description.min' => 'La descripción técnica debe tener al menos 20 caracteres.',
            'technical_description.required' => 'La descripción técnica es obligatoria.',
            'unit_of_measure.required' => 'La unidad de medida es obligatoria.',
            'product_type.required' => 'Debe especificar si es PRODUCTO o SERVICIO.',
            'product_type.in' => 'El tipo debe ser PRODUCTO o SERVICIO.',
            'maximum_quantity.gte' => 'La cantidad máxima debe ser mayor o igual a la cantidad mínima.',
            'lead_time_days.max' => 'El tiempo de entrega no puede ser mayor a 365 días.',
            'specifications.json' => 'Las especificaciones técnicas deben ser un JSON válido.',
        ];
    }

    /**
     * Configurar validador personalizado.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar que si hay cuenta mayor, también haya sub y subsub
            $hasMajor = !empty($this->account_major);
            $hasSub = !empty($this->account_sub);
            $hasSubsub = !empty($this->account_subsub);

            if ($hasMajor || $hasSub || $hasSubsub) {
                if (!$hasMajor) {
                    $validator->errors()->add('account_major', 'Si proporciona estructura contable, la Cuenta Mayor es obligatoria.');
                }
                if (!$hasSub) {
                    $validator->errors()->add('account_sub', 'Si proporciona estructura contable, la Subcuenta es obligatoria.');
                }
                if (!$hasSubsub) {
                    $validator->errors()->add('account_subsub', 'Si proporciona estructura contable, la Subsubcuenta es obligatoria.');
                }
            }
        });
    }
}
