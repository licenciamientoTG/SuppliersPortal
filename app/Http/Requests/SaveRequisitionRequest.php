<?php

namespace App\Http\Requests;

use App\Models\BudgetCedula;
use App\Models\CostCenter;
use App\Models\ProductService;
use App\Models\RequisitionItem;
use App\Services\BudgetCedulaCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requisition = $this->route('requisition');

        if ($requisition) {
            return $requisition->canBeEdited();
        }

        return true;
    }

    public function rules(): array
    {
        $requisition = $this->route('requisition');
        $isUpdate = $requisition !== null;

        $rules = [
            'department_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
            ],

            'receiving_location_id' => [
                $isUpdate ? 'sometimes' : 'required',
                'required',
                'integer',
                'exists:receiving_locations,id',
            ],

            'required_date' => [
                'nullable',
                'date',
                'after_or_equal:today',
            ],

            'description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'items' => [
                $isUpdate ? 'sometimes' : 'required',
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_service_id' => [
                'required',
                'integer',
                'exists:products_services,id',
            ],

            'items.*.expense_category_id' => [
                'required',
                'integer',
                'exists:expense_categories,id',
            ],

            'items.*.budget_cedula_id' => [
                'required',
                'integer',
                'exists:budget_cedulas,id',
            ],

            'items.*.cost_center_id' => [
                'required',
                'integer',
                'exists:cost_centers,id',
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0.001',
                'max:999999.999',
            ],

            'items.*.description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'items.*.unit' => [
                'nullable',
                'string',
                'max:30',
            ],

            'items.*.item_category' => [
                'nullable',
                'string',
                'max:120',
            ],

            'items.*.product_code' => [
                'nullable',
                'string',
                'max:80',
            ],

            'items.*.suggested_vendor_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where(function ($q) {
                    $q->whereNotExists(function ($sub) {
                        $sub->from('sat_efos_69b as e')
                            ->whereColumn('e.rfc', 'suppliers.rfc')
                            ->whereIn('e.situation', ['Definitivo', 'Presunto']);
                    });
                }),
            ],

            'items.*.notes' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'items.*.line_number' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];

        if (! $isUpdate) {
            $rules['company_id'] = [
                'required',
                'integer',
                'exists:companies,id',
            ];
        }

        if ($isUpdate) {
            $rules['items.*.id'] = [
                'nullable',
                'integer',
                'exists:requisition_items,id',
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'La compañía es obligatoria.',
            'company_id.exists' => 'La compañía seleccionada no existe.',
            'purchase_type.required' => 'El tipo de compra es obligatorio.',
            'cost_center_id.required' => 'El centro de costos es obligatorio.',
            'cost_center_id.exists' => 'El centro de costos no pertenece a la compañía seleccionada.',
            'department_id.exists' => 'El departamento seleccionado no existe.',
            'receiving_location_id.required' => 'La ubicación de recepción es obligatoria.',
            'receiving_location_id.exists' => 'La ubicación de recepción seleccionada no existe.',
            'required_date.after_or_equal' => 'La fecha requerida no puede ser anterior a hoy.',
            'description.max' => 'La descripción no puede exceder 500 caracteres.',
            'items.required' => 'Debe agregar al menos una partida a la requisición (RN-003).',
            'items.min' => 'Debe agregar al menos una partida a la requisición (RN-003).',
            'items.*.product_service_id.required' => 'Debe seleccionar un producto del catálogo (RN-001).',
            'items.*.product_service_id.exists' => 'El producto seleccionado no existe en el catálogo.',
            'items.*.expense_category_id.required' => 'La categoría de gasto es obligatoria (RN-010A).',
            'items.*.expense_category_id.exists' => 'La categoría de gasto seleccionada no existe.',
            'items.*.budget_cedula_id.required' => 'La subcategoría presupuestal es obligatoria.',
            'items.*.budget_cedula_id.exists' => 'La subcategoría presupuestal seleccionada no existe.',
            'items.*.cost_center_id.required' => 'El centro de costo de la partida es obligatorio.',
            'items.*.cost_center_id.exists' => 'El centro de costo de la partida no existe.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.numeric' => 'La cantidad debe ser un número.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a cero.',
            'items.*.quantity.max' => 'La cantidad no puede exceder 999,999.999',
            'items.*.description.max' => 'La descripción de la partida no puede exceder 1000 caracteres.',
            'items.*.unit.max' => 'La unidad de medida no puede exceder 30 caracteres.',
            'items.*.notes.max' => 'Las observaciones no pueden exceder 1000 caracteres.',
            'items.*.suggested_vendor_id.exists' => 'El proveedor seleccionado no es válido o está listado como EFOS.',
            'items.*.id.exists' => 'La partida seleccionada no existe.',
            'items.*.line_number.min' => 'El número de línea debe ser mayor a cero.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $requisition = $this->route('requisition');
            $isUpdate = $requisition !== null;

            if ($isUpdate && ! $requisition->canBeEdited()) {
                $validator->errors()->add(
                    'status',
                    'No se puede editar una requisición en estado "'.$requisition->status->label().'". Solo se pueden editar requisiciones en borrador o pausadas.'
                );
            }

            if ($this->cost_center_id) {
                $costCenter = CostCenter::find($this->cost_center_id);
                $purchaseType = $this->purchase_type
                    ?: $requisition?->primaryPurchaseType();

                if (! $this->user()->costCenters()
                    ->where('cost_centers.id', $this->cost_center_id)
                    ->where('cost_center_user.is_active', true)
                    ->exists()) {
                    $validator->errors()->add(
                        'cost_center_id',
                        'El centro de costos seleccionado no esta asignado a tu usuario.'
                    );
                }

                if ($costCenter && $purchaseType && ($costCenter->purchase_type?->value ?? $costCenter->purchase_type) !== $purchaseType) {
                    $validator->errors()->add(
                        'cost_center_id',
                        'El centro de costos no coincide con el tipo de compra seleccionado.'
                    );
                }

                $fiscalYear = ($isUpdate && $requisition)
                    ? (int) ($requisition->created_at?->year ?? date('Y'))
                    : (int) date('Y');

                if ($costCenter && ! $costCenter->hasAnnualBudget($fiscalYear)) {
                    $validator->errors()->add(
                        'cost_center_id',
                        'El centro de costos no tiene presupuesto asignado para el año fiscal (RN-004).'
                    );
                }
            }

            if ($this->items && is_array($this->items)) {
                $productIds = array_column($this->items, 'product_service_id');
                $duplicates = array_diff_assoc($productIds, array_unique($productIds));

                if (! empty($duplicates)) {
                    $validator->errors()->add(
                        'items',
                        'No puede agregar el mismo producto más de una vez. Si necesita diferentes cantidades, ajuste la partida existente.'
                    );
                }
            }

            if ($this->items && is_array($this->items)) {
                $catalogService = app(BudgetCedulaCatalogService::class);
                $fiscalYear = ($isUpdate && $requisition)
                    ? (int) ($requisition->created_at?->year ?? date('Y'))
                    : (int) date('Y');

                foreach ($this->items as $index => $item) {
                    $expenseCategoryId = (int) ($item['expense_category_id'] ?? 0);
                    $budgetCedulaId = (int) ($item['budget_cedula_id'] ?? 0);
                    $itemCostCenterId = (int) ($item['cost_center_id'] ?? 0);

                    if (! $expenseCategoryId || ! $budgetCedulaId || ! $itemCostCenterId) {
                        continue;
                    }

                    if (! $this->user()->costCenters()
                        ->where('cost_centers.id', $itemCostCenterId)
                        ->where('cost_center_user.is_active', true)
                        ->exists()) {
                        $validator->errors()->add(
                            "items.{$index}.cost_center_id",
                            'El centro de costo de la partida no está asignado a tu usuario.'
                        );

                        continue;
                    }

                    $itemCostCenter = CostCenter::find($itemCostCenterId);
                    $companyId = $isUpdate
                        ? (int) $requisition->company_id
                        : (int) $this->company_id;

                    if ($itemCostCenter && (int) $itemCostCenter->company_id !== $companyId) {
                        $validator->errors()->add(
                            "items.{$index}.cost_center_id",
                            'El centro de costo de la partida no pertenece a la compaÃ±Ã­a de la requisiciÃ³n.'
                        );

                        continue;
                    }

                    if ($itemCostCenter && ! $itemCostCenter->hasAnnualBudget($fiscalYear)) {
                        $validator->errors()->add(
                            "items.{$index}.cost_center_id",
                            'El centro de costo de la partida no tiene presupuesto para el aÃ±o fiscal.'
                        );

                        continue;
                    }

                    $belongsToCategory = BudgetCedula::query()
                        ->whereKey($budgetCedulaId)
                        ->where('expense_category_id', $expenseCategoryId)
                        ->exists();

                    if (! $belongsToCategory) {
                        $validator->errors()->add(
                            "items.{$index}.budget_cedula_id",
                            'La subcategoría presupuestal no pertenece a la categoría de gasto seleccionada.'
                        );

                        continue;
                    }

                    if (! $catalogService->isValidCedulaForContext(
                        $itemCostCenterId,
                        $expenseCategoryId,
                        $budgetCedulaId,
                        $fiscalYear
                    )) {
                        $validator->errors()->add(
                            "items.{$index}.budget_cedula_id",
                            'La subcategoría presupuestal no está configurada para este centro de costo y ejercicio fiscal.'
                        );
                    }
                }
            }

            if ($isUpdate && $this->items && is_array($this->items)) {
                foreach ($this->items as $index => $item) {
                    if (isset($item['id']) && ! empty($item['id'])) {
                        $existingItem = RequisitionItem::find($item['id']);

                        if ($existingItem && (int) $existingItem->requisition_id !== (int) $requisition->id) {
                            $validator->errors()->add(
                                "items.{$index}.id",
                                'La partida no pertenece a esta requisición.'
                            );
                        }
                    }
                }

                foreach ($this->items as $index => $item) {
                    if (isset($item['product_service_id']) && isset($item['quantity'])) {
                        $product = ProductService::find($item['product_service_id']);

                        if ($product) {
                            if ($product->minimum_quantity && $item['quantity'] < $product->minimum_quantity) {
                                $validator->errors()->add(
                                    "items.{$index}.quantity",
                                    "La cantidad debe ser mayor o igual a {$product->minimum_quantity} {$product->unit_of_measure}."
                                );
                            }

                            if ($product->maximum_quantity && $item['quantity'] > $product->maximum_quantity) {
                                $validator->errors()->add(
                                    "items.{$index}.quantity",
                                    "La cantidad no puede exceder {$product->maximum_quantity} {$product->unit_of_measure}."
                                );
                            }
                        }
                    }
                }
            }
        });
    }

    protected function failedAuthorization()
    {
        $requisition = $this->route('requisition');

        if ($requisition && ! $requisition->canBeEdited()) {
            abort(403, 'No se puede editar una requisición en estado "'.$requisition->status->label().'".');
        }

        abort(403, 'No tiene permisos para editar esta requisición.');
    }
}
