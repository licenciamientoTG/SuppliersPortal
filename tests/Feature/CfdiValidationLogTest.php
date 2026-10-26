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

    public function test_real_parser_rejection_for_missing_receiver_rfc_logs_its_specific_code_with_partial_data(): void
    {
        $order = PurchaseOrder::factory()->create();
        $supplier = $order->supplier;

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Fecha="2026-05-18T10:30:00" Moneda="MXN" SubTotal="100.00" Total="116.00">
  <cfdi:Emisor Rfc="%s" Nombre="Proveedor"/>
  <cfdi:Receptor Nombre="TotalGas"/>
  <cfdi:Complemento>
    <tfd:TimbreFiscalDigital UUID="11111111-2222-3333-4444-555555555555"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;
        $xml = sprintf($xml, strtoupper($supplier->rfc));

        $service = new InvoiceService(new CfdiXmlParser, Mockery::mock(FinancialProvisionService::class));

        try {
            $service->upload(
                supplier: $supplier,
                order: $order,
                xmlFile: UploadedFile::fake()->createWithContent('factura.xml', $xml),
                pdfFile: UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf'),
                uploader: null,
                origin: SupplierInvoice::ORIGIN_SUPPLIER,
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            // esperado
        }

        $this->assertDatabaseHas('cfdi_validation_logs', [
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'issuer_rfc' => strtoupper($supplier->rfc),
            'result' => CfdiValidationLog::RESULT_REJECTED,
            'rejection_code' => CfdiRejectionCode::RFC_RECEPTOR_AUSENTE,
        ]);
    }

    /**
     * No es viable simular una carrera real de dos requests en SQLite in-memory de un solo
     * proceso: se ejercita directamente la unidad responsable (logDuplicateIfRaceLost),
     * reproduciendo el estado que deja una carrera perdida (el INSERT falla pero el UUID
     * ya existe porque el otro intento concurrente ganó).
     */
    public function test_duplicate_insert_failure_logs_uuid_duplicado_when_the_uuid_already_exists(): void
    {
        $order = PurchaseOrder::factory()->create();
        $supplier = $order->supplier;
        SupplierInvoice::create([
            'supplier_id' => $supplier->id,
            'receivable_type' => get_class($order),
            'receivable_id' => $order->id,
            'uuid' => 'RACE-UUID',
            'xml_path' => 'x.xml',
            'pdf_path' => 'x.pdf',
            'issuer_rfc' => strtoupper($supplier->rfc),
            'receiver_rfc' => 'TGA010101AA1',
            'subtotal' => 100.00,
            'iva_amount' => 16.00,
            'total' => 116.00,
            'currency' => 'MXN',
            'uploaded_origin' => SupplierInvoice::ORIGIN_SUPPLIER,
            'status' => SupplierInvoice::STATUS_UPLOADED,
        ]);

        $service = new InvoiceService(Mockery::mock(CfdiXmlParser::class), Mockery::mock(FinancialProvisionService::class));
        $method = new \ReflectionMethod($service, 'logDuplicateIfRaceLost');
        $method->invoke($service, 'RACE-UUID', ['uuid' => 'RACE-UUID', 'issuer_rfc' => strtoupper($supplier->rfc)], ['channel' => CfdiValidationLog::CHANNEL_PORTAL, 'supplier_id' => $supplier->id]);

        $this->assertDatabaseHas('cfdi_validation_logs', [
            'uuid' => 'RACE-UUID',
            'rejection_code' => CfdiRejectionCode::UUID_DUPLICADO,
        ]);
    }

    public function test_insert_failure_for_a_uuid_that_does_not_exist_does_not_log_duplicate(): void
    {
        $service = new InvoiceService(Mockery::mock(CfdiXmlParser::class), Mockery::mock(FinancialProvisionService::class));
        $method = new \ReflectionMethod($service, 'logDuplicateIfRaceLost');
        $method->invoke($service, 'NEVER-EXISTED', ['uuid' => 'NEVER-EXISTED'], ['channel' => CfdiValidationLog::CHANNEL_PORTAL]);

        $this->assertDatabaseCount('cfdi_validation_logs', 0);
    }

    private function serviceParsing(Supplier $supplier, array $data): InvoiceService
    {
        $parser = Mockery::mock(CfdiXmlParser::class);
        $parser->shouldReceive('parse')->andReturn($data);

        return new InvoiceService($parser, Mockery::mock(FinancialProvisionService::class));
    }
}
