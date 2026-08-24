<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxGroupItem extends Model
{
    protected $fillable = [
        'one_goal_id',
        'tax_group_id',
        'tax_code_id',
        'ledger_account_id',
        'one_goal_tax_code_id',
        'one_goal_ledger_account_id',
        'is_iva_base',
        'related_iva_item_one_goal_id',
        'withholding_type_id',
        'is_excluded_from_cfdi',
        'sat_tax_object',
    ];

    protected function casts(): array
    {
        return [
            'tax_group_id' => 'integer',
            'tax_code_id' => 'integer',
            'ledger_account_id' => 'integer',
            'is_iva_base' => 'boolean',
            'is_excluded_from_cfdi' => 'boolean',
        ];
    }

    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(TaxGroup::class);
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }
}
