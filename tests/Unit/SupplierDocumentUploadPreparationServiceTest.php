<?php

namespace Tests\Unit;

use App\Services\SupplierDocumentUploadPreparationService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SupplierDocumentUploadPreparationServiceTest extends TestCase
{
    public function test_it_consolidates_multiple_images_into_one_pdf(): void
    {
        $prepared = (new SupplierDocumentUploadPreparationService)->prepare([
            UploadedFile::fake()->image('pagina-1.jpg', 900, 1200),
            UploadedFile::fake()->image('pagina-2.png', 900, 1200),
        ], 10240);

        try {
            $this->assertSame('application/pdf', $prepared['file']->getClientMimeType());
            $this->assertSame('pdf', $prepared['file']->getClientOriginalExtension());
            $this->assertStringStartsWith('%PDF-', (string) file_get_contents($prepared['temporary_path']));
        } finally {
            @unlink($prepared['temporary_path']);
        }
    }
}
