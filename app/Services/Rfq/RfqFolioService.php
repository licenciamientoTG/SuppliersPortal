<?php

namespace App\Services\Rfq;

use App\Models\Rfq;

/**
 * Generador único de folios de RFQ.
 *
 * Formato: RFQ-YYYYMMDD-#### (secuencia diaria). Sustituye a los dos
 * generadores previos (count() diario propenso a colisión y el secuencial
 * anual de Rfq::nextFolio) tras la decisión de unificar en este formato.
 */
class RfqFolioService
{
    public function next(): string
    {
        $prefix = 'RFQ-'.now()->format('Ymd').'-';

        $lastFolio = Rfq::withTrashed()
            ->where('folio', 'like', $prefix.'%')
            ->orderByDesc('folio')
            ->value('folio');

        $sequence = $lastFolio ? ((int) substr($lastFolio, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
