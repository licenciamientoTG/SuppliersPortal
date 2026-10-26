<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CfdiRejectionCode extends Model
{
    public const RFC_EMISOR_AUSENTE = 'RFC_EMISOR_AUSENTE';

    public const RFC_RECEPTOR_AUSENTE = 'RFC_RECEPTOR_AUSENTE';

    public const RFC_NO_COINCIDE = 'RFC_NO_COINCIDE';

    public const EFOS_69B = 'EFOS_69B';

    public const RFC_RECEPTOR_INVALIDO = 'RFC_RECEPTOR_INVALIDO';

    public const TOTAL_INVALIDO = 'TOTAL_INVALIDO';

    public const UUID_DUPLICADO = 'UUID_DUPLICADO';

    public const XML_MALFORMADO = 'XML_MALFORMADO';

    public const TOLERANCIA_IMPORTE_EXCEDIDA = 'TOLERANCIA_IMPORTE_EXCEDIDA';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'rule_description',
        'supplier_message',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
