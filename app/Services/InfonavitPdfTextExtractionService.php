<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class InfonavitPdfTextExtractionService
{
    public function extract(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if (! is_string($path) || ! is_file($path)) {
            return ['text' => '', 'method' => null];
        }

        $text = $this->extractWithPdfToText($path);
        if ($this->hasUsefulText($text)) {
            return ['text' => $text, 'method' => 'pdftotext'];
        }

        $text = $this->extractWithOcr($path);
        if ($this->hasUsefulText($text)) {
            return ['text' => $text, 'method' => 'ocr'];
        }

        return ['text' => trim($text), 'method' => null];
    }

    private function extractWithPdfToText(string $path): string
    {
        $binary = (string) config('services.pdf.pdftotext_binary', 'pdftotext');

        try {
            $result = Process::timeout(30)->run([$binary, '-layout', '-enc', 'UTF-8', $path, '-']);
        } catch (\Throwable) {
            return '';
        }

        return $result->successful() ? trim($result->output()) : '';
    }

    private function extractWithOcr(string $path): string
    {
        $pdftoppm = (string) config('services.pdf.pdftoppm_binary', 'pdftoppm');
        $tesseract = (string) config('services.pdf.tesseract_binary', 'tesseract');
        $directory = storage_path('app/tmp/infonavit-ocr');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $prefix = $directory.'/pdf-'.Str::uuid();

        try {
            $render = Process::timeout(60)->run([
                $pdftoppm,
                '-png',
                '-r', '220',
                '-f', '1',
                '-l', '3',
                $path,
                $prefix,
            ]);

            if (! $render->successful()) {
                return '';
            }

            $texts = [];
            foreach (glob($prefix.'-*.png') ?: [] as $page) {
                $ocr = Process::timeout(60)->run([
                    $tesseract,
                    $page,
                    'stdout',
                    '-l', config('services.pdf.tesseract_lang', 'spa+eng'),
                ]);

                if ($ocr->successful()) {
                    $texts[] = trim($ocr->output());
                }
            }

            return trim(implode("\n", array_filter($texts)));
        } catch (\Throwable) {
            return '';
        } finally {
            foreach (glob($prefix.'-*.png') ?: [] as $page) {
                @unlink($page);
            }
        }
    }

    private function hasUsefulText(string $text): bool
    {
        $normalized = Str::of($text)->ascii()->lower()->value();

        return str_contains($normalized, 'infonavit')
            || str_contains($normalized, 'estatus cumplimiento')
            || str_contains($normalized, 'sin adeudo')
            || str_contains($normalized, 'con adeudo');
    }
}
