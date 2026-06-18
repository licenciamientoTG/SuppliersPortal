<?php

namespace App\Services;

use App\Support\SupplierFiscalCatalog;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SupplierCsfExtractorService
{
    private const SESSION_KEY = 'supplier_registration_csf_uploads';

    public function __construct(
        private readonly SatQrDataParser $parser,
    ) {
    }

    public function storeTemporaryUpload(UploadedFile $file, Session $session): array
    {
        $token = (string) Str::uuid();
        $path = $file->storeAs('tmp/supplier-csf', $token . '.pdf', 'local');

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

    private function extractFromLocalPath(string $absolutePath): array
    {
        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            throw new RuntimeException('No fue posible leer la constancia fiscal cargada.');
        }

        $satUrl = $this->extractSatUrlFromPdfContents($contents);

        if (! $satUrl) {
            throw new RuntimeException('No fue posible localizar el QR o la URL fiscal dentro del PDF.');
        }

        $response = Http::timeout(20)
            ->withOptions([
                'verify' => config('services.sat.verify_ssl', true),
            ])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 SuppliersPortal SAT CSF Reader',
                'Accept-Language' => 'es-MX,es;q=0.9',
            ])
            ->get($satUrl);

        if (! $response->successful()) {
            throw new RuntimeException('No fue posible consultar la pagina del SAT para validar la constancia.');
        }

        return $this->normalizeParsedResult($this->parser->parse($response->body(), $satUrl));
    }

    private function extractSatUrlFromPdfContents(string $contents): ?string
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

        $candidates = array_values(array_unique(array_filter($candidates, fn ($url) => $this->isSatQrUrl($url))));

        return $candidates[0] ?? null;
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

    private function normalizeParsedResult(array $parsed): array
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

        return [
            'source_url' => $parsed['source_url'] ?? null,
            'rfc' => $rfc,
            'person_type' => $personType,
            'first_name' => $personType === 'fisica' ? $firstName : $companyName,
            'last_name' => $personType === 'fisica' ? $lastName : '',
            'company_name' => $companyName,
            'address' => $this->buildAddress($items),
            'postal_code' => $postalCode,
            'sat_email' => $this->firstValue($items, ['Correo electrónico', 'Correo electronico']),
            'tax_regimes' => $normalizedRegimes,
            'tax_regime_labels' => array_values(array_map(
                fn (array $regime) => trim($regime['code'] . ' - ' . $regime['label']),
                $normalizedRegimes
            )),
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
        foreach ($labels as $label) {
            if (! empty($items[$label][0])) {
                return trim((string) $items[$label][0]);
            }
        }

        return null;
    }

    private function allValues(array $items, array $labels): array
    {
        $values = [];

        foreach ($labels as $label) {
            foreach ($items[$label] ?? [] as $value) {
                $value = trim((string) $value);

                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }
}
