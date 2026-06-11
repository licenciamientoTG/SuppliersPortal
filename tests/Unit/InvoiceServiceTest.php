<?php

namespace Tests\Unit;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\CfdiXmlParser;
use App\Services\FinancialProvisionService;
use App\Services\InvoiceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    public function test_invalid_xml_is_reported_as_validation_error(): void
    {
        $parser = Mockery::mock(CfdiXmlParser::class);
        $parser->shouldReceive('parse')
            ->once()
            ->andThrow(new RuntimeException('El XML de factura no es válido.'));

        $service = new InvoiceService(
            $parser,
            Mockery::mock(FinancialProvisionService::class),
        );

        $supplier = new Supplier([
            'rfc' => 'AAA010101AAA',
        ]);

        $order = new PurchaseOrder([
            'id' => 1,
            'supplier_id' => 1,
            'currency' => 'MXN',
        ]);

        $xmlFile = Mockery::mock(UploadedFile::class);
        $xmlFile->shouldReceive('get')->once()->andReturn('<invalid');

        $pdfFile = Mockery::mock(UploadedFile::class);

        try {
            $service->upload(
                supplier: $supplier,
                order: $order,
                xmlFile: $xmlFile,
                pdfFile: $pdfFile,
                uploader: null,
                origin: 'supplier',
            );

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'xml_file' => ['El XML de factura no es válido.'],
            ], $exception->errors());
        }
    }
}
