<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Presupuesto original de un renglón (mes + cuenta + subcuenta) al aprobar o importar el presupuesto anual. */
class BudgetBaseline extends Model
{
    public const SOURCE_APPROVAL = 'APPROVAL';

    public const SOURCE_IMPORT = 'IMPORT';

    public const SOURCE_RECONSTRUCTED = 'RECONSTRUCTED';

    public const SOURCE_LABELS = [
        self::SOURCE_APPROVAL => 'Aprobación',
        self::SOURCE_IMPORT => 'Importación',
        self::SOURCE_RECONSTRUCTED => 'Reconstruida',
    ];

    protected $fillable = ['annual_budget_id', 'month', 'expense_category_id', 'budget_cedula_id', 'original_amount', 'source', 'captured_at', 'captured_by'];

    protected $casts = ['original_amount' => 'decimal:2', 'captured_at' => 'datetime', 'month' => 'integer'];

    public function annualBudget(): BelongsTo
    {
        return $this->belongsTo(AnnualBudget::class);
    }
}
