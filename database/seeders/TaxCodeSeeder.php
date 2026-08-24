<?php

namespace Database\Seeders;

use App\Models\TaxCode;
use Illuminate\Database\Seeder;

class TaxCodeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->taxCodes() as $taxCode) {
            TaxCode::updateOrCreate(
                ['one_goal_id' => $taxCode['one_goal_id']],
                $taxCode,
            );
        }
    }

    /** @return array<int, array<string, bool|int|float|string>> */
    private function taxCodes(): array
    {
        return [
            $this->code(0, 'SIN ASIGNAR', 0, 1, 0, 0, 0, 15000, false),
            $this->code(1, 'IVA TASA 15%', 15, 1, 1, 0, 0),
            $this->code(2, 'IVA TASA 10%', 10, 1, 1, 0, 0),
            $this->code(3, 'IVA TASA 0%', 0, 1, 1, 0, 0),
            $this->code(4, 'IVA EXENTO', 0, 1, 0, 0, 1),
            $this->code(5, 'RET ISR 10%', -10, 1, 0, 1, 0, false, 15001),
            $this->code(6, 'RET IVA 10%', -10, 1, 1, 1, 0),
            $this->code(7, 'RET IVA 4%', -4, 1, 1, 1, 0),
            $this->code(8, 'I.E.P.S. 5%', 10, 1, 0, 0, 0, 15004),
            $this->code(9, 'RET IVA 6.66%', -6.6667, 1, 1, 1, 0),
            $this->code(10, 'IEPS MAGNA', 0.4369, 2, 0, 0, 0, 15004),
            $this->code(11, 'IEPS PREMIUM', 0.5331, 2, 0, 0, 0, 15004),
            $this->code(12, 'IEPS DIESEL', 0.3626, 2, 0, 0, 0, 15004),
            $this->code(13, 'IVA TASA 16%', 16, 1, 1, 0, 0),
            $this->code(14, 'IVA TASA 8%', 8, 1, 1, 0, 0),
            $this->code(15, 'RET IVA 10.66%', -10.6667, 1, 1, 1, 0),
            $this->code(16, 'RET IVA 7.33%', -7.3333, 1, 1, 1, 0),
            $this->code(17, 'IVA TASA 1%', 1, 1, 1, 0, 0),
            $this->code(18, 'IEPS 25%', 25, 1, 0, 0, 0, 15004),
            $this->code(19, 'IEPS 30%', 30, 1, 0, 0, 0, 15004),
            $this->code(20, 'IEPS 53%', 53, 1, 0, 0, 0, 15004),
            $this->code(21, 'IEPS 25%', 25, 1, 0, 0, 0, 15004),
            $this->code(22, 'IEPS 30%', 30, 1, 0, 0, 0, 15004),
            $this->code(23, 'IEPS 53%', 53, 1, 0, 0, 0, 15004),
            $this->code(24, 'IVA TRASL 11%', 11, 1, 1, 0, 0),
            $this->code(25, 'RET IVA 6%', -6, 1, 1, 1, 0),
            $this->code(26, 'RET IVA 3%', -3, 1, 1, 1, 0),
            $this->code(27, 'RET IVA 5.33%', -5.33, 1, 1, 1, 0),
            $this->code(28, 'IVA TASA 3%', 3, 1, 1, 0, 0),
            $this->code(29, 'RET IVA 16%', -16, 1, 1, 1, 0),
            $this->code(30, 'RET ISR 25%', -25, 1, 0, 1, 0, false, 15001),
            $this->code(31, 'RET ISR 1.25%', -1.25, 1, 0, 1, 0, false, 15001),
        ];
    }

    /** @return array<string, bool|int|float|string> */
    private function code(
        int $oneGoalId,
        string $name,
        float $rate,
        int $sourceType,
        int $isVat,
        int $isWithholding,
        int $isExempt,
        int $oneGoalTaxTypeId = 15000,
        bool $isSelectable = true,
    ): array {
        return [
            'one_goal_id' => $oneGoalId,
            'name' => $name,
            'rate' => $rate,
            'calculation_type' => $sourceType === 2 ? 'fixed_quota' : 'percentage',
            'one_goal_tax_type_id' => $oneGoalTaxTypeId,
            'is_vat' => (bool) $isVat,
            'is_withholding' => (bool) $isWithholding,
            'is_exempt' => (bool) $isExempt,
            'is_active' => true,
            'is_selectable' => $isSelectable,
        ];
    }
}
