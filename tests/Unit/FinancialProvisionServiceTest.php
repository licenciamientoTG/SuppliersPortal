<?php

namespace Tests\Unit;

use App\Models\DirectPurchaseOrderItem;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Services\FinancialProvisionService;
use PHPUnit\Framework\TestCase;

class FinancialProvisionServiceTest extends TestCase
{
    public function test_calculates_provision_only_from_conforming_received_items(): void
    {
        $conformingOrderItem = new DirectPurchaseOrderItem([
            'quantity' => 5,
            'unit_price' => 100,
            'iva_rate' => 16,
        ]);

        $nonConformingOrderItem = new DirectPurchaseOrderItem([
            'quantity' => 5,
            'unit_price' => 200,
            'iva_rate' => 16,
        ]);

        $conforming = new ReceptionItem([
            'quantity_received' => 2,
            'conformity' => ReceptionItem::CONFORMITY_OK,
        ]);
        $conforming->setRelation('receivableItem', $conformingOrderItem);

        $nonConforming = new ReceptionItem([
            'quantity_received' => 1,
            'conformity' => ReceptionItem::CONFORMITY_FAIL,
        ]);
        $nonConforming->setRelation('receivableItem', $nonConformingOrderItem);

        $reception = new Reception;
        $reception->setRelation('items', collect([$conforming, $nonConforming]));

        $amount = (new FinancialProvisionService)->calculateProvisionAmount($reception);

        $this->assertSame(232.0, $amount);
    }

    public function test_invoice_amount_tolerance_is_five_cents(): void
    {
        $this->assertSame(0.05, FinancialProvisionService::INVOICE_AMOUNT_TOLERANCE);
    }
}
