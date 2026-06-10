<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequisitionItem extends Model
{
    use HasFactory;
    protected $table = 'requisition_items';

    protected $fillable = [
        'requisition_id',
        'product_service_id',
        'line_number',
        'item_category',
        'product_code',
        'description',
        'expense_category_id',
        'budget_cedula_id',
        'cost_center_id',
        'quantity',
        'unit',
        'suggested_vendor_id',
        'notes',

        // Contratos comerciales
        'contract_id',
        'contract_product_id',
        'unit_price',
        'currency_code',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'expense_category_id' => 'integer',
        'budget_cedula_id' => 'integer',
        'cost_center_id' => 'integer',
        'quantity' => 'decimal:3',
    ];

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /**
     * Relación con la requisición padre.
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /**
     * Relación con el producto/servicio del catálogo.
     * RN-001: Solo productos del catálogo pueden ser requisitados.
     */
    public function productService(): BelongsTo
    {
        return $this->belongsTo(ProductService::class, 'product_service_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductService::class, 'product_service_id');
    }

    /**
     * Relación con la categoría de gasto presupuestal.
     */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * Relación con la subcategoría presupuestal (cédula).
     */
    public function budgetCedula(): BelongsTo
    {
        return $this->belongsTo(BudgetCedula::class, 'budget_cedula_id');
    }

    /**
     * Relación con el centro de costo asignado a esta partida.
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * Relación con el proveedor sugerido del catálogo.
     * Este es solo una sugerencia, no vinculante.
     */
    public function suggestedVendor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'suggested_vendor_id');
    }

    /**
     * Contrato comercial asignado a esta partida.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Contract::class);
    }

    /**
     * Producto de contrato vinculado a esta partida (snapshot de precio).
     */
    public function contractProduct(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ContractProduct::class);
    }

    // =========================================================================
    // MÉTODOS DE VALIDACIÓN
    // =========================================================================

    /**
     * Verifica que la partida tenga todos los campos obligatorios.
     */
    public function isValid(): bool
    {
        return ! empty($this->product_service_id)
            && ! empty($this->expense_category_id)
            && ! empty($this->budget_cedula_id)
            && ! empty($this->cost_center_id)
            && $this->quantity > 0;
    }

    // =========================================================================
    // MÉTODOS DE INFORMACIÓN
    // =========================================================================

    /**
     * Obtiene la descripción completa del ítem.
     */
    public function getFullDescription(): string
    {
        if ($this->product_code) {
            return "[{$this->product_code}] {$this->description}";
        }

        return $this->description;
    }

    /**
     * Obtiene información resumida de la partida para mostrar en listas.
     */
    public function getSummary(): array
    {
        return [
            'line_number' => $this->line_number,
            'description' => $this->getFullDescription(),
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'expense_category' => $this->expenseCategory?->name ?? 'Sin categoría',
            'budget_cedula' => $this->budgetCedula?->name ?? 'Sin subcategoría',
            'cost_center' => $this->costCenter?->name ?? 'Sin centro de costo',
            'notes' => $this->notes,
            'suggested_vendor' => $this->suggestedVendor?->name ?? null,
        ];
    }

    /**
     * Obtiene la cantidad formateada.
     */
    public function getFormattedQuantity(): string
    {
        return number_format($this->quantity, 3) . ' ' . $this->unit;
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeOfRequisition($query, int $requisitionId)
    {
        return $query->where('requisition_id', $requisitionId);
    }

    public function scopeOfExpenseCategory($query, int $categoryId)
    {
        return $query->where('expense_category_id', $categoryId);
    }

    public function scopeOrderedByLine($query)
    {
        return $query->orderBy('line_number');
    }

    public function scopeWithRelations($query)
    {
        return $query->with([
            'productService',
            'expenseCategory',
            'budgetCedula',
            'costCenter',
            'suggestedVendor',
        ]);
    }

    // =========================================================================
    // EVENTOS DEL MODELO
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (! $item->line_number) {
                $maxLine = static::where('requisition_id', $item->requisition_id)
                    ->max('line_number');
                $item->line_number = ($maxLine ?? 0) + 1;
            }

            if ($item->product_service_id && ! $item->description) {
                $product = ProductService::find($item->product_service_id);
                if ($product) {
                    $item->description = $item->description ?? $product->getRequisitionDescription();
                    $item->item_category = $item->item_category ?? $product->product_type;
                    $item->product_code = $item->product_code ?? $product->code;
                    $item->unit = $item->unit ?? $product->unit_of_measure;
                }
            }
        });

        static::deleted(function ($item) {
            $remainingItems = static::where('requisition_id', $item->requisition_id)
                ->orderBy('line_number')
                ->get();

            $lineNumber = 1;
            foreach ($remainingItems as $remaining) {
                if ($remaining->line_number !== $lineNumber) {
                    $remaining->line_number = $lineNumber;
                    $remaining->save();
                }
                $lineNumber++;
            }
        });
    }

    // =========================================================================
    // MÉTODOS ESTÁTICOS ÚTILES
    // =========================================================================

    public static function nextLineNumber(int $requisitionId): int
    {
        $maxLine = static::where('requisition_id', $requisitionId)
            ->max('line_number');

        return ($maxLine ?? 0) + 1;
    }

    public static function countByRequisition(int $requisitionId): int
    {
        return static::where('requisition_id', $requisitionId)->count();
    }

    public static function groupedByCategory(int $requisitionId): array
    {
        $items = static::with(['expenseCategory', 'budgetCedula'])
            ->where('requisition_id', $requisitionId)
            ->orderBy('expense_category_id')
            ->get();

        $grouped = [];

        foreach ($items as $item) {
            $key = $item->expense_category_id;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'expense_category_id' => $item->expense_category_id,
                    'expense_category_name' => $item->expenseCategory?->name ?? 'Sin categoría',
                    'items_count' => 0,
                    'items' => [],
                ];
            }

            $grouped[$key]['items_count']++;
            $grouped[$key]['items'][] = $item;
        }

        return array_values($grouped);
    }

    public static function validateRequisitionItems(int $requisitionId): array
    {
        $items = static::where('requisition_id', $requisitionId)->get();
        $errors = [];

        if ($items->isEmpty()) {
            $errors[] = 'La requisición debe tener al menos una partida (RN-003)';

            return $errors;
        }

        foreach ($items as $item) {
            if (! $item->product_service_id) {
                $errors[] = "Partida {$item->line_number}: No tiene producto del catálogo (RN-001)";
            }

            if (! $item->expense_category_id) {
                $errors[] = "Partida {$item->line_number}: Falta categoría de gasto (RN-010A)";
            }

            if (! $item->budget_cedula_id) {
                $errors[] = "Partida {$item->line_number}: Falta subcategoría presupuestal.";
            }

            if (! $item->cost_center_id) {
                $errors[] = "Partida {$item->line_number}: Falta el centro de costo.";
            }

            if ($item->quantity <= 0) {
                $errors[] = "Partida {$item->line_number}: La cantidad debe ser mayor a cero";
            }
        }

        return $errors;
    }

    public function quotationGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            QuotationGroup::class,
            'quotation_group_items',
            'requisition_item_id',
            'quotation_group_id'
        )
            ->withPivot(['notes', 'sort_order'])
            ->withTimestamps();
    }

    public function rfqs(): HasMany
    {
        return $this->hasMany(Rfq::class);
    }

    public function rfqResponses(): HasMany
    {
        return $this->hasMany(RfqResponse::class);
    }

    public function isInGroup(): bool
    {
        return $this->quotationGroups()->exists();
    }

    public function hasQuotations(): bool
    {
        return $this->rfqResponses()->exists();
    }
}
