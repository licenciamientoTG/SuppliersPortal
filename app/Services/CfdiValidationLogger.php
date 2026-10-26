<?php

namespace App\Services;

use App\Models\CfdiValidationLog;
use App\Models\Company;

/**
 * Escribe la bitácora de intentos de carga de CFDI (RF-01). Un solo punto de
 * escritura usado por InvoiceService (carga) y FinancialProvisionService
 * (rechazo por tolerancia de importe), para no duplicar el cálculo de
 * attempted_at/company_id en cada llamador.
 */
class CfdiValidationLogger
{
    public function log(array $attributes): CfdiValidationLog
    {
        return CfdiValidationLog::create(array_merge([
            'attempted_at' => now(),
        ], $attributes));
    }

    public function resolveCompanyId(?string $receiverRfc): ?int
    {
        if ($receiverRfc === null || $receiverRfc === '') {
            return null;
        }

        return Company::query()
            ->whereRaw('UPPER(rfc) = ?', [strtoupper(trim($receiverRfc))])
            ->value('id');
    }
}
