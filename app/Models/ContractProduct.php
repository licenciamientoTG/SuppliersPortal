<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'product_service_id',
        'unit_price',
        'currency_code',
        'unit_of_measure',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function product()
    {
        return $this->belongsTo(ProductService::class, 'product_service_id');
    }
}
