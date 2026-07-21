<?php

namespace Tests\Unit;

use App\Models\SupplierDocumentType;
use App\Services\ComplianceDocumentQrExtractor;
use App\Services\DocumentQrReaderService;
use App\Services\InfonavitPdfTextExtractionService;
use App\Services\SupplierCsfExtractorService;
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

        $extraction = (new ComplianceDocumentQrExtractor($reader, new InfonavitPdfTextExtractionService, Mockery::mock(SupplierCsfExtractorService::class)))->extract(
            UploadedFile::fake()->create('opinion.pdf', 10, 'application/pdf'),
            new SupplierDocumentType(['code' => 'opinion_sat'])
        );

        $this->assertSame('2026-07-01', $extraction->issuedAt->toDateString());
        $this->assertSame('SGT220531R7A', $extraction->metadata['rfc']);
        $this->assertSame('POSITIVA', $extraction->metadata['compliance_status']);
    }

    public function test_it_uses_the_csf_service_that_validates_both_sat_qrs(): void
    {
        $reader = Mockery::mock(DocumentQrReaderService::class);
        $reader->shouldNotReceive('read');
        $csf = Mockery::mock(SupplierCsfExtractorService::class);
        $csf->shouldReceive('extractFromFile')->once()->andReturn([
            'issued_at' => '2024-08-07',
            'issue_date_extraction_data' => [
                'rfc' => 'RIVM5307255Q2',
                'csf_cedula_qr_validated' => true,
                'csf_validation_qr_validated' => true,
                'csf_qr_rfc_matches' => true,
            ],
        ]);

        $extraction = (new ComplianceDocumentQrExtractor($reader, new InfonavitPdfTextExtractionService, $csf))->extract(
            UploadedFile::fake()->create('csf.pdf', 10, 'application/pdf'),
            new SupplierDocumentType(['code' => 'constancia_fiscal'])
        );

        $this->assertSame('2024-08-07', $extraction->issuedAt->toDateString());
        $this->assertSame('RIVM5307255Q2', $extraction->metadata['rfc']);
        $this->assertTrue($extraction->metadata['csf_cedula_qr_validated']);
        $this->assertTrue($extraction->metadata['csf_validation_qr_validated']);
    }

    public function test_it_extracts_rfc_date_and_opinion_from_imss_qr(): void
    {
        $reader = Mockery::mock(DocumentQrReaderService::class);
        $reader->shouldReceive('read')->once()->andReturn([
            '||Invocante:portalimssdigital|Tramite:Carta de No Adeudo Art. 32D|Fecha:13 de enero 2025, 17:04:46|Folio:17368094870261299930620|RFC:SGT220531R7A|Opinion:POSITIVA|FechaInicioVigencia:13 de enero 2025, 17:04:46|FechaFinVigencia:13 de enero de 2025, 23:59:59||',
        ]);

        $extraction = (new ComplianceDocumentQrExtractor($reader, new InfonavitPdfTextExtractionService, Mockery::mock(SupplierCsfExtractorService::class)))->extract(
            UploadedFile::fake()->create('imss.pdf', 10, 'application/pdf'),
            new SupplierDocumentType(['code' => 'opinion_imss'])
        );

        $this->assertSame('2025-01-13', $extraction->issuedAt->toDateString());
        $this->assertSame('SGT220531R7A', $extraction->metadata['rfc']);
        $this->assertSame('POSITIVA', $extraction->metadata['compliance_status']);
    }

    public function test_it_extracts_infonavit_from_pdf_text_without_qr(): void
    {
        $reader = Mockery::mock(DocumentQrReaderService::class);
        $reader->shouldNotReceive('read');

        $textExtractor = Mockery::mock(InfonavitPdfTextExtractionService::class);
        $textExtractor->shouldReceive('extract')->once()->andReturn([
            'method' => 'pdftotext',
            'text' => implode("\n", [
                'Numero de oficio: 0000071108/2025',
                'Fecha de oficio: 13/01/2025',
                'RFC: SGT220531R7A',
                'Estatus cumplimiento: Sin adeudo',
                'Fecha fin de vigencia: 12/02/2025',
            ]),
        ]);

        $extraction = (new ComplianceDocumentQrExtractor($reader, $textExtractor, Mockery::mock(SupplierCsfExtractorService::class)))->extract(
            UploadedFile::fake()->create('infonavit.pdf', 10, 'application/pdf'),
            new SupplierDocumentType(['code' => 'opinion_infonavit'])
        );

        $this->assertSame('2025-01-13', $extraction->issuedAt->toDateString());
        $this->assertSame('SGT220531R7A', $extraction->metadata['rfc']);
        $this->assertSame('POSITIVA', $extraction->metadata['compliance_status']);
        $this->assertSame('infonavit_pdftotext', $extraction->metadata['validation_method']);
    }
}
