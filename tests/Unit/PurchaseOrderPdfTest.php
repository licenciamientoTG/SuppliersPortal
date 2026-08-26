<?php

namespace Tests\Unit;

use App\Models\PurchaseOrder;
use App\Models\QuotationSummary;
use App\Models\User;
use Tests\TestCase;

class PurchaseOrderPdfTest extends TestCase
{
    public function test_it_uses_the_quotation_approval_actor_when_the_purchase_order_has_no_direct_approver(): void
    {
        $authorizer = new User([
            'name' => 'Autorizador del comparativo',
            'job_title' => 'Director de Finanzas',
        ]);

        $summary = (new QuotationSummary)->setRelation('approver', $authorizer);
        $purchaseOrder = (new PurchaseOrder([
            'folio' => 'OC-TEST-001',
            'currency' => 'MXN',
            'subtotal' => 0,
            'iva_amount' => 0,
            'total' => 0,
        ]))
            ->setRelation('items', collect())
            ->setRelation('quotationSummary', $summary);

        $html = view('purchase-orders.pdf', [
            'purchaseOrder' => $purchaseOrder,
            'logoPath' => public_path('images/logos/Logo.png'),
        ])->render();

        $this->assertStringContainsString('Autorizador del comparativo', $html);
        $this->assertStringContainsString('Director de Finanzas', $html);
    }
}
