<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Zxing\QrReader;

class DocumentQrReaderService
{
    /** @return list<string> */
    public function read(UploadedFile $file): array
    {
        if (! class_exists(QrReader::class)) {
            throw new RuntimeException('El lector QR no esta disponible en el servidor.');
        }

        $path = $file->getRealPath();
        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('No fue posible leer el archivo cargado.');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('No fue posible leer el archivo cargado.');
        }

        $values = $this->urlsEmbeddedInPdf($contents);

        if (strtolower($file->getClientOriginalExtension()) !== 'pdf') {
            $values[] = $this->readImage($path);
        } else {
            foreach ($this->pdfImageCandidates($contents) as $image) {
                $temp = storage_path('app/tmp/document-qr/'.Str::uuid().'.jpg');
                if (! is_dir(dirname($temp))) {
                    mkdir(dirname($temp), 0777, true);
                }

                file_put_contents($temp, $image);
                try {
                    $values[] = $this->readImage($temp);
                } finally {
                    @unlink($temp);
                }
            }

            if ($values === []) {
                $values = array_merge($values, $this->readPdfPagesWithImagick($path));
            }
            if ($values === []) {
                $values = array_merge($values, $this->readPdfPagesWithPoppler($path));
            }
        }

        return array_values(array_unique(array_filter($values, fn ($value) => is_string($value) && $value !== '')));
    }

    private function readImage(string $path): ?string
    {
        $dimensions = @getimagesize($path);
        $pixels = is_array($dimensions) ? $dimensions[0] * $dimensions[1] : 0;
        $value = $pixels > 0 && $pixels <= 2_000_000
            ? $this->decodeQr($path)
            : null;

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));
        if (! $image) {
            return null;
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);
            $regions = [
                [(int) ($width * .03), (int) ($height * .73), (int) ($width * .25), (int) ($height * .23)],
                [0, (int) ($height * .68), (int) ($width * .4), (int) ($height * .32)],
                [0, (int) ($height * .55), (int) ($width * .5), (int) ($height * .45)],
                [(int) ($width * .5), (int) ($height * .55), (int) ($width * .5), (int) ($height * .45)],
                [0, 0, (int) ($width * .5), (int) ($height * .5)],
                [(int) ($width * .5), 0, (int) ($width * .5), (int) ($height * .5)],
            ];

            foreach ($regions as [$x, $y, $cropWidth, $cropHeight]) {
                $crop = @imagecrop($image, [
                    'x' => $x,
                    'y' => $y,
                    'width' => $cropWidth,
                    'height' => $cropHeight,
                ]);
                if (! $crop) {
                    continue;
                }

                $temp = storage_path('app/tmp/document-qr/'.Str::uuid().'.png');
                if (! is_dir(dirname($temp))) {
                    mkdir(dirname($temp), 0777, true);
                }
                try {
                    $cropPixels = imagesx($crop) * imagesy($crop);
                    $baseScale = min(1, sqrt(300_000 / max(1, $cropPixels)));
                    foreach (array_unique([$baseScale, $baseScale * .75, $baseScale * .5]) as $scale) {
                        $candidate = $scale >= .99
                            ? $crop
                            : imagescale($crop, max(1, (int) (imagesx($crop) * $scale)));
                        if (! $candidate) {
                            continue;
                        }

                        imagepng($candidate, $temp);
                        $value = $this->decodeQr($temp);
                        if ($candidate !== $crop) {
                            imagedestroy($candidate);
                        }
                        if (is_string($value) && trim($value) !== '') {
                            return trim($value);
                        }
                    }
                } finally {
                    imagedestroy($crop);
                    @unlink($temp);
                }
            }
        } finally {
            imagedestroy($image);
        }

        return null;
    }

    private function decodeQr(string $path): mixed
    {
        $errorLevel = error_reporting();
        error_reporting($errorLevel & ~E_DEPRECATED);
        set_error_handler(static fn () => true, E_DEPRECATED);
        try {
            return (new QrReader($path))->text();
        } finally {
            restore_error_handler();
            error_reporting($errorLevel);
        }
    }

    /** @return list<string> */
    private function readPdfPagesWithImagick(string $path): array
    {
        if (! class_exists(\Imagick::class)) {
            return [];
        }

        $pdf = new \Imagick;
        $values = [];
        try {
            $pdf->setResolution(220, 220);
            $pdf->readImage($path);
            foreach ($pdf as $page) {
                $page->setImageFormat('png');
                $temp = storage_path('app/tmp/document-qr/'.Str::uuid().'.png');
                if (! is_dir(dirname($temp))) {
                    mkdir(dirname($temp), 0777, true);
                }
                try {
                    $page->writeImage($temp);
                    $values[] = $this->readImage($temp);
                } finally {
                    @unlink($temp);
                }
            }
        } catch (\ImagickException) {
            // La lectura embebida conserva compatibilidad con servidores sin Ghostscript.
        } finally {
            $pdf->clear();
            $pdf->destroy();
        }

        return array_values(array_filter($values));
    }

    /** @return list<string> */
    private function readPdfPagesWithPoppler(string $path): array
    {
        $binary = (string) config('services.pdf.pdftoppm_binary', 'pdftoppm');
        $directory = storage_path('app/tmp/document-qr');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $prefix = $directory.'/pdf-'.Str::uuid();
        try {
            $result = Process::timeout(30)->run([
                $binary,
                '-png',
                '-r', '220',
                '-f', '1',
                '-l', '5',
                $path,
                $prefix,
            ]);

            if (! $result->successful()) {
                return [];
            }

            $values = [];
            foreach (glob($prefix.'-*.png') ?: [] as $page) {
                $values[] = $this->readImage($page);
            }

            return array_values(array_filter($values));
        } catch (\Throwable) {
            return [];
        } finally {
            foreach (glob($prefix.'-*.png') ?: [] as $page) {
                @unlink($page);
            }
        }
    }

    /** @return list<string> */
    private function urlsEmbeddedInPdf(string $contents): array
    {
        $values = [];
        if (preg_match_all('/\/URI\s*\((.*?)\)/s', $contents, $matches)) {
            foreach ($matches[1] as $value) {
                $values[] = $this->decodePdfString($value);
            }
        }
        if (preg_match_all('/https?:\/\/[^\s<>)\\\\]+/i', $contents, $matches)) {
            foreach ($matches[0] as $value) {
                $values[] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return array_values(array_filter($values, fn ($value) => filter_var($value, FILTER_VALIDATE_URL) && parse_url($value, PHP_URL_QUERY)));
    }

    private function decodePdfString(string $value): string
    {
        $value = preg_replace_callback('/\\\\([0-7]{1,3})/', fn (array $match) => chr(octdec($match[1])), $value) ?? $value;

        return html_entity_decode(str_replace(['\\\\(', '\\\\)', '\\\\\\\\'], ['(', ')', '\\\\'], trim($value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @return list<string> */
    private function pdfImageCandidates(string $contents): array
    {
        preg_match_all('/\d+\s+\d+\s+obj\s*<<(?P<dictionary>.*?)>>\s*stream\r?\n/s', $contents, $matches, PREG_OFFSET_CAPTURE);
        $images = [];

        foreach ($matches[0] as $index => $whole) {
            $dictionary = $matches['dictionary'][$index][0];
            if (! str_contains($dictionary, '/Subtype /Image') || ! preg_match('/\/Length\s+(\d+)/', $dictionary, $length)) {
                continue;
            }

            $stream = substr($contents, $whole[1] + strlen($whole[0]), (int) $length[1]);
            if (! is_string($stream)) {
                continue;
            }

            $filters = $this->filters($dictionary);
            foreach ($filters as $filter) {
                if ($filter === 'FlateDecode') {
                    $decoded = @zlib_decode($stream);
                    $stream = $decoded === false ? @gzuncompress($stream) : $decoded;
                    if ($stream === false) {
                        continue 2;
                    }
                }
            }

            if (in_array('DCTDecode', $filters, true) && str_starts_with($stream, "\xFF\xD8\xFF")) {
                $images[] = $stream;
            }
        }

        return $images;
    }

    /** @return list<string> */
    private function filters(string $dictionary): array
    {
        if (! preg_match('/\/Filter\s*(\[(?<list>.*?)\]|\/(?<single>[A-Za-z0-9]+))/s', $dictionary, $matches)) {
            return [];
        }
        if (! empty($matches['single'])) {
            return [$matches['single']];
        }
        preg_match_all('/\/([A-Za-z0-9]+)/', $matches['list'] ?? '', $filters);

        return $filters[1] ?? [];
    }
}
