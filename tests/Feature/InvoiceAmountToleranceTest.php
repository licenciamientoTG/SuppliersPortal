<?php

namespace Tests\Feature;

use App\Models\DirectPurchaseOrder;
use App\Models\FinancialProvision;
use App\Models\Reception;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Notifications\InvoiceRejectedByToleranceNotification;
use App\Notifications\SupplierInvoiceRejectedNotification;
use App\Services\FinancialProvisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceAmountToleranceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('accounting', 'web');
    }

    public function test_invoice_within_five_cent_tolerance_is_linked_and_invoiced(): void
    {
        $provision = $this->createProvision(1000.00);
        $invoice = $this->createInvoice($provision, 1000.05);

        $result = app(FinancialProvisionService::class)->reconcile($provision, $invoice);

        $this->assertSame(FinancialProvision::STATUS_INVOICED, $result->status);
        $this->assertSame($invoice->id, $result->supplier_invoice_id);
        $this->assertSame(SupplierInvoice::STATUS_LINKED, $invoice->fresh()->status);
    }

    public function test_invoice_beyond_five_cent_tolerance_is_rejected_and_provision_stays_pending(): void
    {
        $provision = $this->createProvision(1000.00);
        $invoice = $this->createInvoice($provision, 1000.06);

        $result = app(FinancialProvisionService::class)->reconcile($provision, $invoice);

        $this->assertSame(FinancialProvision::STATUS_PENDING_INVOICE, $result->status);
        $this->assertNull($result->supplier_invoice_id);

        $invoice->refresh();
        $this->assertSame(SupplierInvoice::STATUS_REJECTED, $invoice->status);
        $this->assertNotNull($invoice->rejected_at);
        $this->assertStringContainsString('0.06', $invoice->rejection_reason);
        $this->assertStringContainsString('orden de compra', $invoice->rejection_reason);
    }

    public function test_rejected_invoice_notifies_supplier_and_accounting(): void
    {
        Notification::fake();

        $accountant = User::factory()->create();
        $accountant->assignRole('accounting');

        $provision = $this->createProvision(500.00);
        $invoice = $this->createInvoice($provision, 500.50);

        app(FinancialProvisionService::class)->reconcile($provision, $invoice);

        Notification::assertSentTo($invoice->supplier, SupplierInvoiceRejectedNotification::class);
        Notification::assertSentTo($accountant, InvoiceRejectedByToleranceNotification::class);
    }

    public function test_rejected_invoice_cannot_be_reselected_to_link_against_a_provision(): void
    {
        $provision = $this->createProvision(1000.00);
        $invoice = $this->createInvoice($provision, 1000.06);

        app(FinancialProvisionService::class)->reconcile($provision, $invoice);

        $this->assertSame(SupplierInvoice::STATUS_REJECTED, $invoice->fresh()->status);

        $compatible = SupplierInvoice::where('supplier_id', $provision->supplier_id)
            ->where('receivable_type', $provision->receivable_type)
            ->where('receivable_id', $provision->receivable_id)
            ->whereNull('financial_provision_id')
            ->where('status', SupplierInvoice::STATUS_UPLOADED)
            ->get();

        $this->assertTrue($compatible->doesntContain('id', $invoice->id));
    }

    private function createProvision(float $amount): FinancialProvision
    {
        $order = DirectPurchaseOrder::factory()->create();

        $reception = Reception::create([
            'folio' => Reception::generateNextFolio(),
            'receivable_type' => DirectPurchaseOrder::class,
            'receivable_id' => $order->id,
            'receiving_location_id' => $order->receiving_location_id,
            'received_by' => User::factory()->create()->id,
            'status' => Reception::STATUS_COMPLETED,
            'received_at' => now(),
        ]);

        return FinancialProvision::create([
            'reception_id' => $reception->id,
            'receivable_type' => DirectPurchaseOrder::class,
            'receivable_id' => $order->id,
            'supplier_id' => $order->supplier_id,
            'provision_amount' => $amount,
            'currency' => 'MXN',
            'status' => FinancialProvision::STATUS_PENDING_INVOICE,
            'provisioned_at' => now(),
        ]);
    }

    private function createInvoice(FinancialProvision $provision, float $total): SupplierInvoice
    {
        $iva = round($total - round($total / 1.16, 2), 2);

        return SupplierInvoice::create([
            'supplier_id' => $provision->supplier_id,
            'receivable_type' => $provision->receivable_type,
            'receivable_id' => $provision->receivable_id,
            'uuid' => (string) Str::uuid(),
            'xml_path' => 'supplier-invoices/test.xml',
            'pdf_path' => 'supplier-invoices/test.pdf',
            'issuer_rfc' => 'AAA010101AAA',
            'receiver_rfc' => 'TGA010101AAA',
            'subtotal' => round($total - $iva, 2),
            'iva_amount' => $iva,
            'total' => $total,
            'currency' => 'MXN',
            'issued_at' => now(),
            'uploaded_origin' => SupplierInvoice::ORIGIN_SUPPLIER,
            'status' => SupplierInvoice::STATUS_UPLOADED,
        ]);
    }
}
