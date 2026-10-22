<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DirectPurchaseOrder;
use App\Models\FinancialProvision;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\SupplierDocumentRequirement;
use App\Models\SupplierDocumentType;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\CfdiXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceUploadRejectionFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_upload_that_gets_auto_rejected_does_not_report_success(): void
    {
        Storage::fake('public');
        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('accounting', 'web');
        $staff = User::factory()->create();
        $staff->assignRole('superadmin');

        $order = DirectPurchaseOrder::factory()->create();
        $supplier = $order->supplier;
        $this->createPendingProvision($order, 1000.00);
        Company::factory()->create(['rfc' => 'TGA010101AAA', 'is_active' => true]);

        $this->bindParser($supplier, 1000.50);

        $response = $this->actingAs($staff)->post(route('invoices.store'), [
            'supplier_id' => $supplier->id,
            'order_type' => 'direct',
            'order_id' => $order->id,
            'xml_file' => UploadedFile::fake()->createWithContent('factura.xml', '<xml/>'),
            'pdf_file' => UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf'),
        ]);

        $response->assertSessionMissing('success');
        $response->assertSessionHas('error');

        $invoice = SupplierInvoice::firstOrFail();
        $this->assertSame(SupplierInvoice::STATUS_REJECTED, $invoice->status);
        $this->assertStringContainsString($invoice->rejection_reason, session('error'));
    }

    public function test_supplier_upload_that_gets_auto_rejected_does_not_report_success(): void
    {
        Storage::fake('public');
        Role::findOrCreate('accounting', 'web');

        $order = DirectPurchaseOrder::factory()->create();
        $supplier = $order->supplier;
        $this->createPendingProvision($order, 1000.00);
        Company::factory()->create(['rfc' => 'TGA010101AAA', 'is_active' => true]);
        $this->grantPortalAccess($supplier);

        $this->bindParser($supplier, 1000.50);

        $response = $this->actingAs($supplier, 'supplier')->post(route('supplier.invoices.store'), [
            'order_type' => 'direct',
            'order_id' => $order->id,
            'xml_file' => UploadedFile::fake()->createWithContent('factura.xml', '<xml/>'),
            'pdf_file' => UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf'),
        ]);

        $response->assertSessionMissing('success');
        $response->assertSessionHas('error');

        $invoice = SupplierInvoice::firstOrFail();
        $this->assertSame(SupplierInvoice::STATUS_REJECTED, $invoice->status);
        $this->assertStringContainsString($invoice->rejection_reason, session('error'));
    }

    private function grantPortalAccess(Supplier $supplier): void
    {
        SupplierDocumentType::where('is_required', true)->where('is_active', true)->get()
            ->each(fn (SupplierDocumentType $type) => SupplierDocumentRequirement::create([
                'supplier_id' => $supplier->id,
                'supplier_document_type_id' => $type->id,
                'is_enforced' => true,
                'status' => 'pending',
                'due_at' => now()->addDays(14),
            ]));
    }

    private function createPendingProvision(DirectPurchaseOrder $order, float $amount): FinancialProvision
    {
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

    private function bindParser(Supplier $supplier, float $total): void
    {
        $parser = Mockery::mock(CfdiXmlParser::class);
        $parser->shouldReceive('parse')->andReturn([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'issuer_rfc' => strtoupper($supplier->rfc),
            'receiver_rfc' => 'TGA010101AAA',
            'subtotal' => round($total / 1.16, 2),
            'iva_amount' => round($total - round($total / 1.16, 2), 2),
            'total' => $total,
            'currency' => 'MXN',
            'issued_at' => now(),
        ]);

        $this->app->instance(CfdiXmlParser::class, $parser);
    }
}
