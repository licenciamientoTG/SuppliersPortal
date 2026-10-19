<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Foto diaria de un renglón de BudgetPositionService::positions() (RP-01, punto #6). */
class BudgetPositionSnapshot extends Model
{
    protected $guarded = [];

    protected $casts = [
        // Sin cast de fecha: updateOrCreate() compara el valor crudo en el WHERE, y el cast
        // 'date' lo normaliza distinto al grabar que al consultar, rompiendo el upsert por día.
        'fiscal_year' => 'integer',
        'month' => 'integer',
        'payload' => 'array',
    ];
}
