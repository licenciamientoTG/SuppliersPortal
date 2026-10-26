<?php

namespace Tests\Feature;

use App\Models\CfdiRejectionCode;
use App\Models\CfdiValidationLog;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\CfdiXmlParser;
use App\Services\FinancialProvisionService;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CfdiValidationLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_upload_logs_one_row_with_result_accepted_and_the_new_invoice(): void
    {
        Storage::fake('public');
        $order = PurchaseOrder::factory()->create();
        $supplier = $order->supplier;
        $company = Company::factory()->create(['rfc' => 'TGA010101AA1', 'is_active' => true]);

        $invoice = $this->serviceParsing($supplier, [
            'uuid' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
            'issuer_rfc' => strtoupper($supplier->rfc),
            'issuer_name' => 'Proveedor de Pruebas',
            'receiver_rfc' => 'TGA010101AA1',
            'subtotal' => 100.00,
            'iva_amount' => 16.00,
            'total' => 116.00,
            'currency' => 'MXN',
            'issued_at' => now(),
            'cfdi_type' => 'I',
        ])->upload(
            supplier: $supplier,
            order: $order,
            xmlFile: UploadedFile::fake()->createWithContent('factura.xml', '<xml/>'),
            pdfFile: UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf'),
            uploader: null,
            origin: SupplierInvoice::ORIGIN_SUPPLIER,
        );

        $this->assertDatabaseCount('cfdi_validation_logs', 1);
        $this->assertDatabaseHas('cfdi_validation_logs', [
            'uuid' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
            'issuer_rfc' => strtoupper($supplier->rfc),
            'issuer_name' => 'Proveedor de Pruebas',
            'company_id' => $company->id,
            'cfdi_type' => 'I',
            'total' => 116.00,
            'result' => CfdiValidationLog::RESULT_ACCEPTED,
            'rejection_code' => null,
            'channel' => CfdiValidationLog::CHANNEL_PORTAL,
            'supplier_id' => $supplier->id,
            'supplier_invoice_id' => $invoice->id,
        ]);
    }

    public function test_rejection_by_duplicate_uuid_logs_the_specific_code_without_a_second_invoice(): void
    {
        Storage::fake('public');
        $order = PurchaseOrder::factory()->create();
        $supplier = $order->supplier;
        Company::factory()->create(['rfc' => 'TGA010101AA1', 'is_active' => true]);

        $data = [
            'uuid' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
            'issuer_rfc' => strtoupper($supplier->rfc),
            'issuer_name' => null,
            'receiver_rfc' => 'TGA010101AA1',
            'subtotal' => 100.00,
            'iva_amount' => 16.00,
            'total' => 116.00,
            'currency' => 'MXN',
            'issued_at' => now(),
            'cfdi_type' => 'I',
        ];

        $this->serviceParsing($supplier, $data)->upload(
            supplier: $supplier,
            order: $order,
            xmlFile: UploadedFile::fake()->createWithContent('factura.xml', '<xml/>'),
            pdfFile: UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf'),
            uploader: null,
            origin: SupplierInvoice::ORIGIN_SUPPLIER,
        );

        try {
            $this->serviceParsing($supplier, $data)->upload(
                supplier: $supplier,
                order: $order,
                xmlFile: UploadedFile::fake()->createWithContent('factura2.xml', '<xml/>'),
                pdfFile: UploadedFile::fake()->create('factura2.pdf', 10, 'application/pdf'),
                uploader: null,
                origin: SupplierInvoice::ORIGIN_SUPPLIER,
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            // esperado
        }

        $this->assertDatabaseCount('supplier_invoices', 1);
        $this->assertDatabaseCount('cfdi_validation_logs', 2);
        $this->assertDatabaseHas('cfdi_validation_logs', [
            'uuid' => $data['uuid'],
            'result' => CfdiValidationLog::RESULT_REJECTED,
            'rejection_code' => CfdiRejectionCode::UUID_DUPLICADO,
            'supplier_invoice_id' => null,
        ]);
    }

    public function test_rejection_by_efos_listing_logs_the_specific_code(): void
    {
        Storage::fake('public');
        $order = PurchaseOrder::factory()->create();
        $supplier = $order->supplier;
        Company::factory()->create(['rfc' => 'TGA010101AA1', 'is_active' => true]);
        DB::table('sat_efos_69b')->insert([
            'number' => 1,
            'rfc' => $supplier->rfc,
            'company_name' => $supplier->company_name,
            'situation' => 'Definitivo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->serviceParsing($supplier, [
                'uuid' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
                'issuer_rfc' => strtoupper($supplier->rfc),
                'issuer_name' => null,
                'receiver_rfc' => 'TGA010101AA1',
                'subtotal' => 100.00,
                'iva_amount' => 16.00,
                'total' => 116.00,
                'currency' => 'MXN',
                'issued_at' => now(),
                'cfdi_type' => 'I',
            ])->upload(
                supplier: $supplier,
                order: $order,
                xmlFile: UploadedFile::fake()->createWithContent('factura.xml', '<xml/>'),
                pdfFile: UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf'),
                uploader: null,
                origin: SupplierInvoice::ORIGIN_SUPPLIER,
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            // esperado
        }

        $this->assertDatabaseCount('supplier_invoices', 0);
        $this->assertDatabaseHas('cfdi_validation_logs', [
            'result' => CfdiValidationLog::RESULT_REJECTED,
            'rejection_code' => CfdiRejectionCode::EFOS_69B,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_malformed_xml_logs_rejection_without_structured_data(): void
    {
        $order = PurchaseOrder::factory()->create();
        $supplier = $order->supplier;

        $parser = Mockery::mock(CfdiXmlParser::class);
        $parser->shouldReceive('parse')->once()->andThrow(new \RuntimeException('El XML de factura no es válido.'));
        $service = new InvoiceService($parser, Mockery::mock(FinancialProvisionService::class));

        try {
            $service->upload(
                supplier: $supplier,
                order: $order,
                xmlFile: UploadedFile::fake()->createWithContent('factura.xml', '<invalid'),
                pdfFile: UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf'),
                uploader: null,
                origin: SupplierInvoice::ORIGIN_SUPPLIER,
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            // esperado
        }

        $this->assertDatabaseHas('cfdi_validation_logs', [
            'uuid' => null,
            'result' => CfdiValidationLog::RESULT_REJECTED,
            'rejection_code' => CfdiRejectionCode::XML_MALFORMADO,
            'supplier_id' => $supplier->id,
        ]);
    }

    private function serviceParsing(Supplier $supplier, array $data): InvoiceService
    {
        $parser = Mockery::mock(CfdiXmlParser::class);
        $parser->shouldReceive('parse')->andReturn($data);

        return new InvoiceService($parser, Mockery::mock(FinancialProvisionService::class));
    }
}
