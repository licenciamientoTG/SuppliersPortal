<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDepartmentSubaccount extends Model
{
    protected $table = 'product_department_subaccount';

    protected $fillable = [
        'product_service_id',
        'department_id',
        'subaccount_id',
    ];

    public function productService(): BelongsTo
    {
        return $this->belongsTo(ProductService::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function subaccount(): BelongsTo
    {
        return $this->belongsTo(Subaccount::class);
    }
}
