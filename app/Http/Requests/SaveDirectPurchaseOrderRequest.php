<?php

namespace App\Http\Requests;

use App\Models\ProductService;
use App\Models\ReceivingLocation;
use App\Services\BudgetAccessService;
use App\Services\ProductBudgetClassificationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SaveDirectPurchaseOrderRequest extends FormRequest
{
    private array $classificationErrors = [];

    protected function prepareForValidation(): void
    {
        if (! is_array($this->items)) {
            return;
        }

        $service = app(ProductBudgetClassificationService::class);

        $items = collect($this->items)->map(function (array $item, int $index) use ($service) {
            $productId = (int) ($item['product_service_id'] ?? 0);

            if (! $productId) {
                return $item;
            }

            $product = ProductService::query()->find($productId);

            if (! $product) {
                return $item;
            }

            try {
                $classification = $service->resolveForProduct($product, Auth::user()?->department_id);
            } catch (\RuntimeException $exception) {
                $this->classificationErrors["items.{$index}.product_service_id"] = $exception->getMessage();

                return $item;
            }

            if (! app(BudgetAccessService::class)->subaccountIdsFor(Auth::user())->contains($classification['subaccount_id'])) {
                $this->classificationErrors["items.{$index}.product_service_id"] = 'El producto no corresponde a una subcuenta permitida para tu perfil presupuestal.';

                return $item;
            }

            $item['description'] = $product->getRequisitionDescription();
            $item['unit_of_measure'] = $product->unit_of_measure;
            $item['sku'] = $product->code;
            $item['expense_category_id'] = $classification['expense_category_id'];
            $item['budget_cedula_id'] = $classification['budget_cedula_id'];

            return $item;
        })->all();

        $this->merge(['items' => $items]);
    }

    public function authorize(): bool
    {
        $ocd = $this->route('directPurchaseOrder');

        if ($ocd) {
            if (! in_array($ocd->status, ['DRAFT', 'RETURNED'])) {
                return false;
            }

            if ($ocd->created_by !== Auth::id()) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
            // ✅ AGREGADOS: Proveedor y Mes de aplicación
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'company_id' => ['required', 'integer', 'exists:companies,id'],

            // Ubicación de recepción (obligatoria)
            'receiving_location_id' => ['required', 'integer', 'exists:receiving_locations,id'],

            // Justificación
            'justification' => ['required', 'string', 'min:100', 'max:2000'],

            // Condiciones del Proveedor (Opcionales)
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'estimated_delivery_days' => ['nullable', 'integer', 'min:1', 'max:365'],

            // PARTIDAS CON TASA DE IVA
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_service_id' => ['required', 'integer', 'exists:products_services,id'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'items.*.cost_center_id' => ['required', 'integer', 'exists:cost_centers,id'],
            'items.*.expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'items.*.budget_cedula_id' => ['required', 'integer', 'exists:budget_cedulas,id'],
            'items.*.iva_rate' => ['required', 'numeric', 'in:0,8,16'],

            // ✅ AGREGADOS: Campos adicionales de la partida
            'items.*.unit_of_measure' => ['nullable', 'string', 'max:50'],
            'items.*.sku' => ['nullable', 'string', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],

            // Documentos
            'quotation_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'support_documents' => ['nullable', 'array', 'max:5'],
            'support_documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            // Proveedor
            'supplier_id.required' => 'Debe seleccionar un proveedor.',
            'supplier_id.exists' => 'El proveedor seleccionado no existe.',

            // Datos Presupuestales
            'company_id.required' => 'Debe seleccionar una empresa.',
            'company_id.exists' => 'La empresa seleccionada no existe.',

            // Centro de costo por partida
            'items.*.cost_center_id.required' => 'Debe seleccionar un centro de costo para cada partida.',
            'items.*.cost_center_id.exists' => 'El centro de costo seleccionado no existe.',

            // Ubicación de recepción
            'receiving_location_id.required' => 'Debe seleccionar una ubicación de recepción.',
            'receiving_location_id.exists' => 'La ubicación de recepción seleccionada no existe o está inactiva.',

            // Justificación
            'justification.required' => 'La justificación es obligatoria.',
            'justification.min' => 'La justificación debe tener al menos 100 caracteres.',
            'justification.max' => 'La justificación no puede exceder 2000 caracteres.',

            // Partidas
            'items.required' => 'Debe agregar al menos una partida.',
            'items.min' => 'Debe agregar al menos una partida.',
            'items.*.description.required' => 'La descripción de la partida es obligatoria.',
            'items.*.description.max' => 'La descripción no puede exceder 500 caracteres.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a 0.',
            'items.*.unit_price.required' => 'El precio unitario es obligatorio.',
            'items.*.unit_price.min' => 'El precio unitario debe ser mayor a 0.',

            // Categoría de Gasto por partida
            'items.*.expense_category_id.required' => 'Debe seleccionar una cuenta para cada partida.',
            'items.*.expense_category_id.exists' => 'La cuenta seleccionada no existe.',

            // Tasa de IVA
            'items.*.iva_rate.required' => 'Debe seleccionar una tasa de IVA.',
            'items.*.iva_rate.in' => 'La tasa de IVA debe ser 0%, 8% o 16%.',

            // Documentos
            'quotation_file.mimes' => 'La cotización debe ser un archivo PDF o imagen (JPG, PNG).',
            'quotation_file.max' => 'La cotización no puede pesar más de 5MB.',
            'support_documents.max' => 'No puede adjuntar más de 5 documentos de soporte.',
            'support_documents.*.mimes' => 'Los documentos de soporte deben ser PDF, imágenes o documentos de Office.',
            'support_documents.*.max' => 'Cada documento no puede pesar más de 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'proveedor',
            'company_id' => 'empresa',
            'justification' => 'justificación',
            'items.*.cost_center_id' => 'centro de costo',
            'quotation_file' => 'cotización',
            'support_documents' => 'documentos de soporte',
            'items.*.description' => 'descripción',
            'items.*.quantity' => 'cantidad',
            'items.*.unit_price' => 'precio unitario',
            'items.*.expense_category_id' => 'cuenta',
            'items.*.iva_rate' => 'tasa de IVA',
            'items.*.unit_of_measure' => 'unidad de medida',
            'items.*.sku' => 'SKU/Código',
            'items.*.notes' => 'notas del artículo',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('items')) {
                foreach ($this->classificationErrors as $field => $message) {
                    $validator->errors()->add($field, $message);
                }

                $total = $this->calculateTotal();

                if ($total > 250000) {
                    $validator->errors()->add(
                        'total',
                        'El total de la OCD ($'.number_format($total, 2).') excede el límite máximo de $250,000.00 MXN. Para compras mayores debe usar el proceso de requisición regular.'
                    );
                }

                if ($total <= 0) {
                    $validator->errors()->add(
                        'total',
                        'El total de la OCD debe ser mayor a $0.00'
                    );
                }
            }

            if ($this->has('items') && is_array($this->items)) {
                foreach ($this->items as $index => $itemData) {
                    $costCenterId = $itemData['cost_center_id'] ?? null;
                    if (! $costCenterId) {
                        continue;
                    }

                    $costCenter = \App\Models\CostCenter::find($costCenterId);
                    if (! $costCenter) {
                        continue;
                    }

                    if (! $this->user()->costCenters()
                        ->where('cost_centers.id', $costCenter->id)
                        ->where('cost_center_user.is_active', true)
                        ->exists()) {
                        $validator->errors()->add(
                            "items.{$index}.cost_center_id",
                            'El centro de costo seleccionado no está asignado a tu usuario.'
                        );
                    }

                    if ((int) $costCenter->company_id !== (int) $this->company_id) {
                        $validator->errors()->add(
                            "items.{$index}.cost_center_id",
                            'El centro de costo no pertenece a la empresa seleccionada.'
                        );
                    }

                    if ($costCenter->status !== 'ACTIVO' || $costCenter->deleted_at !== null) {
                        $validator->errors()->add(
                            "items.{$index}.cost_center_id",
                            'El centro de costo seleccionado no está disponible.'
                        );
                    }
                }
            }

            if ($this->company_id && $this->receiving_location_id) {
                $locationBelongsToCompany = ReceivingLocation::query()
                    ->whereKey((int) $this->receiving_location_id)
                    ->where('company_id', (int) $this->company_id)
                    ->exists();

                if (! $locationBelongsToCompany) {
                    $validator->errors()->add(
                        'receiving_location_id',
                        'La ubicación de recepción no pertenece a la empresa seleccionada.'
                    );
                }
            }
        });
    }

    protected function calculateTotal(): float
    {
        $subtotal = 0;
        $ivaTotal = 0;

        if ($this->has('items') && is_array($this->items)) {
            foreach ($this->items as $item) {
                $quantity = floatval($item['quantity'] ?? 0);
                $unitPrice = floatval($item['unit_price'] ?? 0);
                $ivaRate = floatval($item['iva_rate'] ?? 16);

                $itemSubtotal = $quantity * $unitPrice;
                $itemIva = $itemSubtotal * ($ivaRate / 100);

                $subtotal += $itemSubtotal;
                $ivaTotal += $itemIva;
            }
        }

        return round($subtotal + $ivaTotal, 2);
    }

    protected function failedAuthorization()
    {
        $ocd = $this->route('directPurchaseOrder');

        if ($ocd) {
            if (! in_array($ocd->status, ['DRAFT', 'RETURNED'])) {
                abort(403, 'Solo se pueden editar OCD en estado Borrador o Devueltas.');
            }

            if ((int) $ocd->created_by !== (int) Auth::id()) {
                abort(403, 'Solo puede editar sus propias OCD.');
            }
        }
    }
}
