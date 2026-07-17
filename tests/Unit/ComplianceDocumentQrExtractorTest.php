<?php

namespace Tests\Unit;

use App\Models\SupplierDocumentType;
use App\Services\ComplianceDocumentQrExtractor;
use App\Services\DocumentQrReaderService;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class ComplianceDocumentQrExtractorTest extends TestCase
{
    public function test_it_extracts_rfc_date_and_positive_opinion_from_sat_qr(): void
    {
        $reader = Mockery::mock(DocumentQrReaderService::class);
        $reader->shouldReceive('read')->once()->andReturn([
            'https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=1&D2=1&D3=26NE9193705_SGT220531R7A_01-07-2026_P',
        ]);

        $extraction = (new ComplianceDocumentQrExtractor($reader))->extract(
            UploadedFile::fake()->create('opinion.pdf', 10, 'application/pdf'),
            new SupplierDocumentType(['code' => 'opinion_sat'])
        );

        $this->assertSame('2026-07-01', $extraction->issuedAt->toDateString());
        $this->assertSame('SGT220531R7A', $extraction->metadata['rfc']);
        $this->assertSame('POSITIVA', $extraction->metadata['compliance_status']);
    }

    public function test_it_extracts_csf_emission_date_from_the_signed_sat_qr(): void
    {
        $reader = Mockery::mock(DocumentQrReaderService::class);
        $reader->shouldReceive('read')->once()->andReturn([
            'https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=0&D2=1&D3=%7C%7C2024%2F08%2F07%7CRIVM5307255Q2%7CCONSTANCIA+DE+SITUACI%C3%93N+FISCAL%7C200001088888800000031%7C%7C_sello',
        ]);

        $extraction = (new ComplianceDocumentQrExtractor($reader))->extract(
            UploadedFile::fake()->create('csf.pdf', 10, 'application/pdf'),
            new SupplierDocumentType(['code' => 'constancia_fiscal'])
        );

        $this->assertSame('2024-08-07', $extraction->issuedAt->toDateString());
        $this->assertSame('RIVM5307255Q2', $extraction->metadata['rfc']);
    }

    public function test_it_extracts_rfc_date_and_opinion_from_imss_qr(): void
    {
        $reader = Mockery::mock(DocumentQrReaderService::class);
        $reader->shouldReceive('read')->once()->andReturn([
            '||Invocante:portalimssdigital|Tramite:Carta de No Adeudo Art. 32D|Fecha:13 de enero 2025, 17:04:46|Folio:17368094870261299930620|RFC:SGT220531R7A|Opinion:POSITIVA|FechaInicioVigencia:13 de enero 2025, 17:04:46|FechaFinVigencia:13 de enero de 2025, 23:59:59||',
        ]);

        $extraction = (new ComplianceDocumentQrExtractor($reader))->extract(
            UploadedFile::fake()->create('imss.pdf', 10, 'application/pdf'),
            new SupplierDocumentType(['code' => 'opinion_imss'])
        );

        $this->assertSame('2025-01-13', $extraction->issuedAt->toDateString());
        $this->assertSame('SGT220531R7A', $extraction->metadata['rfc']);
        $this->assertSame('POSITIVA', $extraction->metadata['compliance_status']);
    }
}
