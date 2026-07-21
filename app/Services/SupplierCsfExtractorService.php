<?php

namespace App\Services;

use App\Support\SupplierFiscalCatalog;
use Carbon\Carbon;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Zxing\QrReader;

class SupplierCsfExtractorService
{
    private const SESSION_KEY = 'supplier_registration_csf_uploads';

    public function __construct(
        private readonly SatQrDataParser $parser,
        private readonly DocumentQrReaderService $qrReader,
    ) {}

    public function storeTemporaryUpload(UploadedFile $file, Session $session): array
    {
        $token = (string) Str::uuid();
        $path = $file->storeAs('tmp/supplier-csf', $token.'.pdf', 'local');

        if (! is_string($path)) {
            throw new RuntimeException('No fue posible resguardar temporalmente la constancia.');
        }

        $parsed = $this->extractFromLocalPath(Storage::disk('local')->path($path));

        $upload = [
            'token' => $token,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: 'application/pdf',
            'size_bytes' => $file->getSize(),
            'parsed' => $parsed,
        ];

        $uploads = $session->get(self::SESSION_KEY, []);
        $uploads[$token] = $upload;
        $session->put(self::SESSION_KEY, $uploads);

        return $upload;
    }

    public function getTemporaryUpload(string $token, Session $session): ?array
    {
        $uploads = $session->get(self::SESSION_KEY, []);

        $upload = $uploads[$token] ?? null;

        if (! is_array($upload)) {
            return null;
        }

        if (! Storage::disk('local')->exists($upload['path'] ?? '')) {
            return null;
        }

        return $upload;
    }

    public function extractFromFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('No fue posible leer la constancia fiscal cargada.');
        }

        return $this->extractFromLocalPath($path, $file);
    }

    public function forgetTemporaryUpload(string $token, Session $session): void
    {
        $uploads = $session->get(self::SESSION_KEY, []);
        $upload = $uploads[$token] ?? null;

        if (is_array($upload) && ! empty($upload['path']) && Storage::disk('local')->exists($upload['path'])) {
            Storage::disk('local')->delete($upload['path']);
        }

        unset($uploads[$token]);
        $session->put(self::SESSION_KEY, $uploads);
    }

    public function persistTemporaryUploadAsDocument(int $supplierId, array $upload): string
    {
        $sourcePath = $upload['path'] ?? null;

        if (! is_string($sourcePath) || ! Storage::disk('local')->exists($sourcePath)) {
            throw new RuntimeException('No fue posible recuperar la constancia temporal del proveedor.');
        }

        $extension = pathinfo((string) ($upload['original_name'] ?? 'csf.pdf'), PATHINFO_EXTENSION) ?: 'pdf';
        $targetPath = sprintf(
            'suppliers/%d/documents/%s.%s',
            $supplierId,
            Str::uuid(),
            strtolower($extension)
        );

        Storage::disk('public')->put($targetPath, Storage::disk('local')->get($sourcePath));

        return $targetPath;
    }

    private function extractFromLocalPath(string $absolutePath, ?UploadedFile $uploadedFile = null): array
    {
        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            throw new RuntimeException('No fue posible leer la constancia fiscal cargada.');
        }

        $isUploadedImage = $uploadedFile
            && strtolower($uploadedFile->getClientOriginalExtension()) !== 'pdf';
        $satUrls = $isUploadedImage ? [] : $this->extractSatUrlsFromPdfContents($contents);
        if ($uploadedFile) {
            $satUrls = array_values(array_unique(array_merge(
                $satUrls,
                $this->extractSatUrlsFromUploadedFile($uploadedFile)
            )));
        }
        $qrPair = $this->csfQrPair($satUrls);
        $cedulaQrUrl = $qrPair['cedula'];
        $validationQrUrl = $qrPair['validation'];

        if (! $cedulaQrUrl) {
            throw new RuntimeException('La constancia debe incluir el QR de la cedula fiscal.');
        }

        if (! $validationQrUrl) {
            throw new RuntimeException('La constancia debe incluir el QR de validacion de la constancia.');
        }

        try {
            $response = Http::timeout(20)
                ->withOptions([
                    'verify' => config('services.sat.verify_ssl', true),
                    'curl' => [
                        CURLOPT_SSL_CIPHER_LIST => config('services.sat.cipher_list', 'DEFAULT@SECLEVEL=1'),
                    ],
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 SuppliersPortal SAT CSF Reader',
                    'Accept-Language' => 'es-MX,es;q=0.9',
                ])
                ->get($cedulaQrUrl);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('No fue posible consultar la pagina del SAT para validar la constancia.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('No fue posible consultar la pagina del SAT para validar la constancia.');
        }

        $parsed = $this->parser->parse($response->body(), $cedulaQrUrl);
        $issueDate = $this->extractIssueDateFromSatUrls([$validationQrUrl], $parsed['rfc'] ?? null);

        if (! $issueDate) {
            throw new RuntimeException('No fue posible validar la fecha de emision y el RFC desde el QR de validacion.');
        }

        $cedulaRfc = strtoupper((string) ($parsed['rfc'] ?? ''));
        $validationRfc = strtoupper((string) ($issueDate['metadata']['rfc'] ?? ''));
        if ($cedulaRfc === '' || $validationRfc === '' || ! hash_equals($cedulaRfc, $validationRfc)) {
            throw new RuntimeException('El RFC de la cedula fiscal no coincide con el RFC del QR de validacion.');
        }

        $issueDate['metadata'] = array_merge($issueDate['metadata'], [
            'csf_cedula_qr_url' => $cedulaQrUrl,
            'csf_validation_qr_url' => $validationQrUrl,
            'csf_cedula_rfc' => $cedulaRfc,
            'csf_validation_rfc' => $validationRfc,
            'csf_cedula_qr_validated' => true,
            'csf_validation_qr_validated' => true,
            'csf_qr_rfc_matches' => true,
        ]);

        return $this->normalizeParsedResult($parsed, $issueDate);
    }

    private function extractSatUrlFromPdfContents(string $contents): ?string
    {
        return $this->preferredSatQrUrl($this->extractSatUrlsFromPdfContents($contents));
    }

    private function extractSatUrlsFromPdfContents(string $contents): array
    {
        $candidates = [];

        if (preg_match_all('/\/URI\s*\((.*?)\)/s', $contents, $matches)) {
            foreach ($matches[1] as $rawUri) {
                $decoded = $this->decodePdfLiteralString($rawUri);
                if ($this->isSatQrUrl($decoded)) {
                    $candidates[] = $decoded;
                }
            }
        }

        if (preg_match_all('/https?:\/\/siat\.sat\.gob\.mx\/app\/qr\/faces\/pages\/mobile\/validadorqr\.jsf\?[^\s<>)\\\\]+/i', $contents, $matches)) {
            foreach ($matches[0] as $rawUrl) {
                $candidates[] = str_replace('&amp;', '&', $rawUrl);
            }
        }

        foreach ($this->extractSatUrlsWithQrReader($contents) as $qrUrl) {
            $candidates[] = $qrUrl;
        }

        if ($candidates === []) {
            $embeddedUrl = $this->extractSatUrlFromEmbeddedImage($contents);
            if ($embeddedUrl) {
                $candidates[] = $embeddedUrl;
            }
        }

        return array_values(array_unique(array_filter($candidates, fn ($url) => $this->isSatQrUrl($url))));
    }

    private function extractSatUrlsWithQrReader(string $contents): array
    {
        $tempPath = storage_path('app/tmp/supplier-csf/pdf-'.Str::uuid().'.pdf');
        $directory = dirname($tempPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($tempPath, $contents);

        try {
            $file = new UploadedFile($tempPath, basename($tempPath), 'application/pdf', null, true);

            return array_values(array_filter(
                $this->qrReader->read($file),
                fn ($payload) => is_string($payload) && $this->isSatQrUrl($payload)
            ));
        } catch (\Throwable) {
            return [];
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function extractSatUrlsFromUploadedFile(UploadedFile $file): array
    {
        try {
            return array_values(array_filter(
                $this->qrReader->read($file),
                fn ($payload) => is_string($payload) && $this->isSatQrUrl($payload)
            ));
        } catch (\Throwable) {
            return [];
        }
    }

    private function preferredSatQrUrl(array $urls): ?string
    {
        if ($urls === []) {
            return null;
        }

        $score = function (string $url): int {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $d1 = (string) ($query['D1'] ?? '');

            return match ($d1) {
                '10', '0' => 0,
                '26' => 10,
                default => 5,
            };
        };

        usort($urls, fn (string $a, string $b) => $score($a) <=> $score($b));

        return $urls[0];
    }

    /** @return array{cedula: ?string, validation: ?string} */
    private function csfQrPair(array $urls): array
    {
        $pair = ['cedula' => null, 'validation' => null];

        foreach ($urls as $url) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $d1 = (string) ($query['D1'] ?? '');

            if (in_array($d1, ['10', '0'], true) && ! $pair['cedula']) {
                $pair['cedula'] = $url;
            }

            if ($d1 === '26' && ! $pair['validation']) {
                $pair['validation'] = $url;
            }
        }

        return $pair;
    }

    private function extractIssueDateFromSatUrls(array $urls, ?string $expectedRfc): ?array
    {
        foreach ($urls as $url) {
            $fromPayload = $this->extractCsfIssueDateFromQrPayload($url);

            if ($fromPayload) {
                return $this->buildIssueDateMetadata($fromPayload, $expectedRfc, $url, 'sat_csf_qr_payload');
            }
        }

        foreach ($urls as $url) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            if (($query['D1'] ?? null) !== '26') {
                continue;
            }

            $fromPage = $this->extractCsfIssueDateFromSatPage($url);

            if ($fromPage) {
                return $this->buildIssueDateMetadata($fromPage, $expectedRfc, $url, 'sat_csf_cadena_original');
            }
        }

        return null;
    }

    private function extractCsfIssueDateFromQrPayload(string $url): ?array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $data = urldecode((string) ($query['D3'] ?? ''));

        return $this->extractCsfIssueDateFromText($data);
    }

    private function extractCsfIssueDateFromSatPage(string $url): ?array
    {
        try {
            $response = Http::timeout(20)
                ->withOptions([
                    'verify' => config('services.sat.verify_ssl', true),
                    'curl' => [
                        CURLOPT_SSL_CIPHER_LIST => config('services.sat.cipher_list', 'DEFAULT@SECLEVEL=1'),
                    ],
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 SuppliersPortal SAT CSF Reader',
                    'Accept-Language' => 'es-MX,es;q=0.9',
                ])
                ->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->extractCsfIssueDateFromText(html_entity_decode(strip_tags($response->body()), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function extractCsfIssueDateFromText(string $text): ?array
    {
        if (! preg_match('/\|\|(\d{4})\/(\d{2})\/(\d{2})(?:\s+\d{2}:\d{2}:\d{2})?\|([A-Z&\x{00D1}]{3,4}\d{6}[A-Z0-9]{3})\|CONSTANCIA DE SITUACI/iu', $text, $match)) {
            return null;
        }

        return [
            'issued_at' => Carbon::createFromFormat('Y-m-d', "{$match[1]}-{$match[2]}-{$match[3]}")->startOfDay(),
            'rfc' => strtoupper($match[4]),
        ];
    }

    private function buildIssueDateMetadata(array $result, ?string $expectedRfc, string $sourceUrl, string $method): array
    {
        $rfc = strtoupper((string) ($result['rfc'] ?? ''));
        $expectedRfc = strtoupper((string) $expectedRfc);

        return [
            'issued_at' => $result['issued_at'],
            'metadata' => [
                'status' => 'extracted',
                'document_code' => 'constancia_fiscal',
                'issued_at' => $result['issued_at']->toDateString(),
                'rfc' => $rfc,
                'rfc_matches_supplier' => $expectedRfc === '' || hash_equals($expectedRfc, $rfc),
                'compliance_status' => null,
                'compliance_is_positive' => true,
                'source_url' => $sourceUrl,
                'validation_method' => $method,
            ],
        ];
    }

    private function decodePdfLiteralString(string $value): string
    {
        $value = preg_replace_callback('/\\\\([0-7]{1,3})/', function (array $matches): string {
            return chr(octdec($matches[1]));
        }, $value) ?? $value;

        $value = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $value);

        return html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function isSatQrUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        return $host === 'siat.sat.gob.mx'
            && is_string($path)
            && str_contains($path, '/app/qr/faces/pages/mobile/validadorqr.jsf');
    }

    private function extractSatUrlFromEmbeddedImage(string $contents): ?string
    {
        $this->assertQrDependenciesAreAvailable();

        foreach ($this->extractPdfImageCandidates($contents) as $index => $image) {
            $tempPath = storage_path('app/tmp/supplier-csf/qr-image-'.Str::uuid().'-'.$index.'.jpg');
            $directory = dirname($tempPath);

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($tempPath, $image['bytes']);

            try {
                $qrUrl = $this->scanQrFromImage($tempPath);

                if ($qrUrl && $this->isSatQrUrl($qrUrl)) {
                    return $qrUrl;
                }
            } finally {
                if (is_file($tempPath)) {
                    @unlink($tempPath);
                }
            }
        }

        return null;
    }

    private function assertQrDependenciesAreAvailable(): void
    {
        if (! class_exists(QrReader::class)) {
            throw new RuntimeException('El lector QR no esta disponible en el servidor.');
        }

        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('El servidor no cuenta con la extension GD requerida para leer la constancia fiscal.');
        }
    }

    private function extractPdfImageCandidates(string $contents): array
    {
        $pattern = '/(\d+)\s+(\d+)\s+obj\s*<<(?P<dict>.*?)>>\s*stream\r?\n/s';
        preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE);

        $images = [];

        foreach ($matches[0] as $index => $wholeMatch) {
            $dictionary = $matches['dict'][$index][0];

            if (! str_contains($dictionary, '/Subtype /Image')) {
                continue;
            }

            if (! preg_match('/\/Length\s+(\d+)/', $dictionary, $lengthMatch)) {
                continue;
            }

            $length = (int) $lengthMatch[1];
            $streamStart = $wholeMatch[1] + strlen($wholeMatch[0]);
            $stream = substr($contents, $streamStart, $length);

            if (! is_string($stream) || $stream === '') {
                continue;
            }

            $filters = $this->extractFiltersFromDictionary($dictionary);
            $bytes = $this->decodeImageStreamForQr($stream, $filters);

            if ($bytes === null || $bytes === '') {
                continue;
            }

            $images[] = [
                'bytes' => $bytes,
            ];
        }

        return $images;
    }

    private function extractFiltersFromDictionary(string $dictionary): array
    {
        if (! preg_match('/\/Filter\s*(\[(?<array>.*?)\]|\/(?<single>[A-Za-z0-9]+))/s', $dictionary, $matches)) {
            return [];
        }

        if (! empty($matches['single'])) {
            return [$matches['single']];
        }

        preg_match_all('/\/([A-Za-z0-9]+)/', $matches['array'] ?? '', $filterMatches);

        return $filterMatches[1] ?? [];
    }

    private function decodeImageStreamForQr(string $stream, array $filters): ?string
    {
        $current = $stream;

        foreach ($filters as $filter) {
            if ($filter === 'FlateDecode') {
                $decoded = @zlib_decode($current);

                if ($decoded === false) {
                    $decoded = @gzuncompress($current);
                }

                if ($decoded === false) {
                    return null;
                }

                $current = $decoded;

                continue;
            }

            if ($filter === 'DCTDecode') {
                return $current;
            }
        }

        if (str_starts_with($current, "\xFF\xD8\xFF")) {
            return $current;
        }

        return null;
    }

    private function scanQrFromImage(string $imagePath): ?string
    {
        $reader = new QrReader($imagePath);
        $text = $reader->text();

        if (is_string($text) && $text !== '') {
            return trim($text);
        }

        $image = @imagecreatefromstring((string) file_get_contents($imagePath));

        if (! $image) {
            return null;
        }

        try {
            $crops = [
                ['x' => 0, 'y' => 0, 'width' => 300, 'height' => 300],
                ['x' => 0, 'y' => 0, 'width' => 260, 'height' => 320],
                ['x' => 20, 'y' => 40, 'width' => 200, 'height' => 220],
                ['x' => 30, 'y' => 70, 'width' => 170, 'height' => 190],
            ];

            foreach ($crops as $crop) {
                $cropped = @imagecrop($image, $crop);

                if (! $cropped) {
                    continue;
                }

                $tempCropPath = storage_path('app/tmp/supplier-csf/qr-crop-'.Str::uuid().'.jpg');

                try {
                    imagejpeg($cropped, $tempCropPath, 100);
                    $cropText = (new QrReader($tempCropPath))->text();

                    if (is_string($cropText) && trim($cropText) !== '') {
                        return trim($cropText);
                    }
                } finally {
                    imagedestroy($cropped);

                    if (is_file($tempCropPath)) {
                        @unlink($tempCropPath);
                    }
                }
            }
        } finally {
            imagedestroy($image);
        }

        return null;
    }

    private function normalizeParsedResult(array $parsed, ?array $issueDate = null): array
    {
        $rfc = strtoupper((string) ($parsed['rfc'] ?? ''));

        if (strlen($rfc) < 12 || strlen($rfc) > 13) {
            throw new RuntimeException('No fue posible identificar un RFC valido dentro de la constancia.');
        }

        $personType = strlen($rfc) === 13 ? 'fisica' : 'moral';
        $items = $parsed['items_by_label'] ?? [];

        $firstName = $this->firstValue($items, ['Nombre']);
        $lastNameParts = array_filter([
            $this->firstValue($items, ['Apellido Paterno']),
            $this->firstValue($items, ['Apellido Materno']),
        ]);
        $lastName = trim(implode(' ', $lastNameParts));

        $companyName = $personType === 'fisica'
            ? trim(implode(' ', array_filter([$firstName, $lastName])))
            : ($this->firstValue($items, ['Denominación/Razón Social', 'Denominacion/Razon Social']) ?? '');

        if ($companyName === '') {
            $companyName = $parsed['summary']['nombre_completo'] ?? '';
        }

        $postalCode = preg_replace('/\D/', '', (string) $this->firstValue($items, ['CP'])) ?: '';

        $normalizedRegimes = $this->normalizeTaxRegimes(
            $personType,
            $this->allValues($items, ['Régimen', 'Regimen'])
        );

        $resolvedFirstName = $personType === 'fisica'
            ? ($firstName ?: $companyName)
            : ($firstName ?: '');

        $resolvedLastName = $lastName;

        return [
            'source_url' => $parsed['source_url'] ?? null,
            'rfc' => $rfc,
            'person_type' => $personType,
            'first_name' => $resolvedFirstName,
            'last_name' => $resolvedLastName,
            'company_name' => $companyName,
            'address' => $this->buildAddress($items),
            'postal_code' => $postalCode,
            'sat_email' => $this->firstValue($items, ['Correo electrónico', 'Correo electronico']),
            'tax_regimes' => $normalizedRegimes,
            'tax_regime_labels' => array_values(array_map(
                fn (array $regime) => trim($regime['code'].' - '.$regime['label']),
                $normalizedRegimes
            )),
            'issued_at' => $issueDate ? $issueDate['issued_at']->toDateString() : null,
            'issue_date_extraction_data' => $issueDate['metadata'] ?? null,
            'raw_sections' => $parsed['sections'] ?? [],
        ];
    }

    private function buildAddress(array $items): string
    {
        $streetParts = array_filter([
            $this->firstValue($items, ['Tipo de vialidad']),
            $this->firstValue($items, ['Nombre de la vialidad']),
        ]);

        $numberParts = array_filter([
            $this->firstValue($items, ['Número exterior', 'Numero exterior']),
            $this->firstValue($items, ['Número interior', 'Numero interior']),
        ]);

        $locationParts = array_filter([
            $this->firstValue($items, ['Colonia']),
            $this->firstValue($items, ['Municipio o delegación', 'Municipio o delegacion']),
            $this->firstValue($items, ['Entidad Federativa']),
        ]);

        $segments = [];

        if ($streetParts !== []) {
            $segments[] = implode(' ', $streetParts);
        }

        if ($numberParts !== []) {
            $segments[] = implode(' ', $numberParts);
        }

        if ($locationParts !== []) {
            $segments[] = implode(', ', $locationParts);
        }

        return trim(implode(', ', array_filter($segments)));
    }

    private function normalizeTaxRegimes(string $personType, array $labels): array
    {
        $catalog = collect(SupplierFiscalCatalog::regimesFor($personType))
            ->mapWithKeys(fn (array $regime) => [$this->normalizeText($regime['label']) => $regime])
            ->all();

        $normalized = [];

        foreach ($labels as $label) {
            $key = $this->normalizeText($label);

            if (! isset($catalog[$key])) {
                foreach ($catalog as $catalogKey => $catalogRegime) {
                    if (str_contains($key, $catalogKey) || str_contains($catalogKey, $key)) {
                        $regime = $catalogRegime;
                        $normalized[$regime['code']] = [
                            'code' => $regime['code'],
                            'label' => $regime['label'],
                        ];

                        continue 2;
                    }
                }

                continue;
            }

            $regime = $catalog[$key];
            $normalized[$regime['code']] = [
                'code' => $regime['code'],
                'label' => $regime['label'],
            ];
        }

        return array_values($normalized);
    }

    private function normalizeText(?string $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
    }

    private function firstValue(array $items, array $labels): ?string
    {
        $lookup = $this->buildCanonicalItemLookup($items);

        foreach ($labels as $label) {
            if (! empty($items[$label][0])) {
                return trim((string) $items[$label][0]);
            }

            $canonicalLabel = $this->canonicalLabel($label);

            if (! empty($lookup[$canonicalLabel][0])) {
                return trim((string) $lookup[$canonicalLabel][0]);
            }
        }

        return null;
    }

    private function allValues(array $items, array $labels): array
    {
        $values = [];
        $lookup = $this->buildCanonicalItemLookup($items);

        foreach ($labels as $label) {
            foreach ($items[$label] ?? [] as $value) {
                $value = trim((string) $value);

                if ($value !== '') {
                    $values[] = $value;
                }
            }

            $canonicalLabel = $this->canonicalLabel($label);

            foreach ($lookup[$canonicalLabel] ?? [] as $value) {
                $value = trim((string) $value);

                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }

    private function buildCanonicalItemLookup(array $items): array
    {
        $lookup = [];

        foreach ($items as $label => $values) {
            $canonicalLabel = $this->canonicalLabel((string) $label);

            if ($canonicalLabel === '') {
                continue;
            }

            foreach (is_array($values) ? $values : [$values] as $value) {
                $lookup[$canonicalLabel][] = $value;
            }
        }

        return $lookup;
    }

    private function canonicalLabel(?string $label): string
    {
        $label = Str::of($this->normalizeText($label))
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->replaceMatches('/\b(de|del|la|las|el|los|y|o)\b/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        return $label;
    }
}
