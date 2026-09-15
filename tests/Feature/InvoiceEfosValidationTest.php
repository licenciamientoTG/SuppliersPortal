<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\CfdiXmlParser;
use App\Services\FinancialProvisionService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class InvoiceEfosValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_from_supplier_with_definitive_efos_listing_is_rejected(): void
    {
        $this->assertUploadIsBlockedForSituation('Definitivo');
    }

    public function test_invoice_from_supplier_with_presumed_efos_listing_is_rejected(): void
    {
        $this->assertUploadIsBlockedForSituation('Presunto');
    }

    public function test_invoice_from_supplier_with_non_blocking_efos_situation_is_uploaded(): void
    {
        Storage::fake('public');
        $order = PurchaseOrder::factory()->create();
        $supplier = $order->supplier;
        $this->addEfosRecord($supplier, 'Desvirtuado');

        $invoice = $this->serviceParsing($supplier)->upload(
            supplier: $supplier,
            order: $order,
            xmlFile: UploadedFile::fake()->createWithContent('factura.xml', '<xml/>'),
            pdfFile: UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf'),
            uploader: null,
            origin: SupplierInvoice::ORIGIN_FINANCE,
        );

        $this->assertDatabaseHas('supplier_invoices', ['id' => $invoice->id, 'supplier_id' => $supplier->id]);
    }

    private function assertUploadIsBlockedForSituation(string $situation): void
    {
        Storage::fake('public');
        $order = PurchaseOrder::factory()->create();
        $supplier = $order->supplier;
        $this->addEfosRecord($supplier, $situation);

        try {
            $this->serviceParsing($supplier)->upload(
                supplier: $supplier,
                order: $order,
                xmlFile: UploadedFile::fake()->createWithContent('factura.xml', '<xml/>'),
                pdfFile: UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf'),
                uploader: null,
                origin: SupplierInvoice::ORIGIN_SUPPLIER,
            );

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'xml_file' => ["El proveedor aparece en la lista 69-B del SAT (EFOS) con situación {$situation}. No se puede registrar la factura."],
            ], $exception->errors());
        }

        $this->assertDatabaseCount('supplier_invoices', 0);
    }

    private function serviceParsing(Supplier $supplier): InvoiceService
    {
        $parser = Mockery::mock(CfdiXmlParser::class);
        $parser->shouldReceive('parse')->andReturn([
            'uuid' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
            'issuer_rfc' => strtoupper($supplier->rfc),
            'receiver_rfc' => strtoupper(Company::factory()->create(['rfc' => 'TGA010101AAA', 'is_active' => true])->rfc),
            'subtotal' => 100.00,
            'iva_amount' => 16.00,
            'total' => 116.00,
            'currency' => 'MXN',
            'issued_at' => now(),
        ]);

        return new InvoiceService($parser, Mockery::mock(FinancialProvisionService::class));
    }

    private function addEfosRecord(Supplier $supplier, string $situation): void
    {
        DB::table('sat_efos_69b')->insert([
            'number' => 1,
            'rfc' => $supplier->rfc,
            'company_name' => $supplier->company_name,
            'situation' => $situation,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
