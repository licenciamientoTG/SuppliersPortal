<?php

namespace Tests\Unit;

use App\Services\DocumentQrReaderService;
use App\Services\SatQrDataParser;
use App\Services\SupplierCsfExtractorService;
use Mockery;
use Tests\TestCase;

class SupplierCsfExtractorServiceTest extends TestCase
{
    public function test_it_uses_the_rendered_qr_reader_and_prefers_the_full_csf_url(): void
    {
        $reader = Mockery::mock(DocumentQrReaderService::class);
        $reader->shouldReceive('read')->once()->andReturn([
            'https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=26&D2=1&D3=454402966_SGT220531R7A',
            'https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=22070082234_SGT220531R7A',
        ]);

        $service = new SupplierCsfExtractorService(new SatQrDataParser, $reader);
        $method = new \ReflectionMethod($service, 'extractSatUrlFromPdfContents');
        $method->setAccessible(true);

        $url = $method->invoke($service, '%PDF-1.7 without embedded uri');

        $this->assertStringContainsString('D1=10', $url);
        $this->assertStringContainsString('SGT220531R7A', $url);
    }

    public function test_it_extracts_the_csf_issue_date_from_the_original_chain(): void
    {
        $reader = Mockery::mock(DocumentQrReaderService::class);
        $service = new SupplierCsfExtractorService(new SatQrDataParser, $reader);
        $method = new \ReflectionMethod($service, 'extractCsfIssueDateFromText');
        $method->setAccessible(true);

        $result = $method->invoke(
            $service,
            'Cadena Original: ||2026/07/01 09:53:41|SGT220531R7A|CONSTANCIA DE SITUACION FISCAL|200001088888800000041||'
        );

        $this->assertSame('2026-07-01', $result['issued_at']->toDateString());
        $this->assertSame('SGT220531R7A', $result['rfc']);
    }

    public function test_it_identifies_the_required_cedula_and_validation_qrs(): void
    {
        $service = new SupplierCsfExtractorService(new SatQrDataParser, Mockery::mock(DocumentQrReaderService::class));
        $method = new \ReflectionMethod($service, 'csfQrPair');
        $method->setAccessible(true);

        $pair = $method->invoke($service, [
            'https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=26&D2=1&D3=validation',
            'https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=cedula',
        ]);

        $this->assertStringContainsString('D1=10', $pair['cedula']);
        $this->assertStringContainsString('D1=26', $pair['validation']);
    }
}
