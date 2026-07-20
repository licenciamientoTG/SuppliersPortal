<?php

namespace App\Services;

use App\Models\SupplierDocument;
use Carbon\Carbon;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class InfonavitQrValidationService
{
    public function validateDocument(SupplierDocument $document): bool
    {
        $document->loadMissing('supplier');
        $metadata = $document->issue_date_extraction_data ?? [];
        $qrUrl = collect($metadata['qr_payloads'] ?? [])
            ->first(fn ($payload) => parse_url($payload, PHP_URL_HOST) === 'portalmx.infonavit.org.mx');

        if (! is_string($qrUrl) || ! $document->supplier?->rfc) {
            return false;
        }

        $result = $this->validate($qrUrl, $document->supplier->rfc);
        if (! $result) {
            return false;
        }

        $detectedRfc = strtoupper((string) $result['rfc']);
        $expectedRfc = strtoupper((string) $document->supplier->rfc);
        $metadata = array_merge($metadata, $result, [
            'issued_at' => $result['issued_at']->toDateString(),
            'status' => 'extracted',
            'rfc_matches_supplier' => hash_equals($expectedRfc, $detectedRfc),
            'compliance_is_positive' => $result['compliance_status'] === 'POSITIVA',
        ]);

        $document->update([
            'issued_at' => $result['issued_at'],
            'issued_at_source' => 'qr',
            'issue_date_extraction_data' => $metadata,
        ]);

        return true;
    }

    public function validate(string $qrUrl, string $rfc): ?array
    {
        if (! $this->isAllowedUrl($qrUrl)) {
            return null;
        }

        $browserResult = $this->validateWithBrowser($qrUrl, $rfc);
        if ($browserResult) {
            return $browserResult;
        }

        $cookies = new CookieJar;
        $deadline = microtime(true) + $this->timeoutSeconds();
        $lastUrl = $qrUrl;

        do {
            $page = $this->getPage($lastUrl, $cookies);
            if (! $page) {
                return null;
            }

            $lastUrl = $this->effectiveUrl($page) ?? $lastUrl;

            $form = $this->findRfcForm($page->body(), $lastUrl);
            if ($form) {
                break;
            }

            $result = $this->parseResult($page->body(), $rfc, $lastUrl);
            if ($result) {
                return $result;
            }

            if (microtime(true) >= $deadline) {
                return null;
            }

            sleep($this->pollSeconds());
        } while (true);

        $form['fields'][$form['rfc_field']] = strtoupper($rfc);

        try {
            $request = Http::connectTimeout(10)
                ->timeout($this->requestTimeoutSeconds())
                ->withOptions(['cookies' => $cookies])
                ->withHeaders($this->browserHeaders($lastUrl));
            $result = $form['method'] === 'get'
                ? $request->get($form['action'], $form['fields'])
                : $request->asForm()->post($form['action'], $form['fields']);
        } catch (ConnectionException) {
            return null;
        }

        return $result->successful()
            ? $this->parseResult($result->body(), $rfc, $this->effectiveUrl($result) ?? $form['action'])
            : null;
    }

    private function validateWithBrowser(string $qrUrl, string $rfc): ?array
    {
        if (! config('services.infonavit.browser_enabled')) {
            return null;
        }

        $chrome = config('services.infonavit.chrome_binary');
        if (! is_string($chrome) || $chrome === '' || ! is_file($chrome)) {
            return null;
        }

        $script = base_path('resources/scripts/infonavit-validator.mjs');
        if (! is_file($script)) {
            return null;
        }

        $command = [
            (string) config('services.infonavit.node_binary', 'node'),
            $script,
            $qrUrl,
            strtoupper($rfc),
            $chrome,
            (string) ($this->timeoutSeconds() * 1000),
            config('services.infonavit.headless') ? 'headless' : 'headed',
        ];

        if (! config('services.infonavit.headless')) {
            array_unshift($command, '-a');
            array_unshift($command, (string) config('services.infonavit.xvfb_binary', 'xvfb-run'));
        }

        $result = Process::timeout($this->timeoutSeconds() + 15)->run($command);

        if (! $result->successful()) {
            return null;
        }

        $payload = json_decode(trim($result->output()), true);
        if (! is_array($payload) || ($payload['ok'] ?? false) !== true) {
            return null;
        }

        $text = (string) ($payload['raw_text'] ?? '');
        $issuedAt = $this->numericDate((string) ($payload['issued_at'] ?? ''))
            ?? $this->spanishDate((string) ($payload['issued_at'] ?? ''));
        $status = $this->normalizeStatus((string) ($payload['compliance_status'] ?? $text));
        $detectedRfc = strtoupper((string) ($payload['rfc'] ?? ''));

        if (! $issuedAt || ! $status || ! preg_match('/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/u', $detectedRfc)) {
            return null;
        }

        return [
            'issued_at' => $issuedAt,
            'rfc' => $detectedRfc,
            'compliance_status' => $status,
            'source_url' => (string) ($payload['url'] ?? $qrUrl),
            'validation_method' => 'infonavit_browser',
            'folio' => $payload['oficio'] ?? null,
            'valid_until' => $payload['valid_until'] ?? null,
            'bimestre' => $payload['bimestre'] ?? null,
            'legal_name' => $payload['name'] ?? null,
            'total_nrp' => $payload['total_nrp'] ?? null,
            'total_workers' => $payload['total_workers'] ?? null,
        ];
    }

    private function getPage(string $url, CookieJar $cookies): ?Response
    {
        try {
            $page = Http::connectTimeout(10)
                ->timeout($this->requestTimeoutSeconds())
                ->withOptions(['cookies' => $cookies])
                ->withHeaders($this->browserHeaders())
                ->get($url);
        } catch (ConnectionException) {
            return null;
        }

        return $page->successful() ? $page : null;
    }

    private function browserHeaders(?string $referer = null): array
    {
        return array_filter([
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'es-MX,es;q=0.9,en;q=0.8',
            'Referer' => $referer,
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36',
        ]);
    }

    private function effectiveUrl(Response $response): ?string
    {
        $effectiveUri = $response->handlerStats()['url'] ?? null;

        return is_string($effectiveUri) && $this->isAllowedUrl($effectiveUri) ? $effectiveUri : null;
    }

    private function findRfcForm(string $html, string $sourceUrl): ?array
    {
        $dom = new \DOMDocument;
        if (! @$dom->loadHTML($html)) {
            return null;
        }

        foreach ($dom->getElementsByTagName('form') as $form) {
            $fields = [];
            $rfcField = null;

            foreach ($form->getElementsByTagName('input') as $input) {
                $name = trim($input->getAttribute('name'));
                if ($name === '') {
                    continue;
                }
                $fields[$name] = $input->getAttribute('value');
                if (str_contains(Str::lower($name), 'rfc')) {
                    $rfcField = $name;
                }
            }

            if (! $rfcField) {
                continue;
            }

            $action = $this->absoluteUrl($sourceUrl, $form->getAttribute('action'));
            if (! $action || ! $this->isAllowedUrl($action)) {
                return null;
            }

            return [
                'action' => $action,
                'method' => Str::lower($form->getAttribute('method') ?: 'get'),
                'fields' => $fields,
                'rfc_field' => $rfcField,
            ];
        }

        return null;
    }

    private function parseResult(string $html, string $expectedRfc, string $sourceUrl): ?array
    {
        $text = html_entity_decode(
            preg_replace('/<[^>]+>/', ' ', $html) ?? strip_tags($html),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $status = $this->normalizeStatus($text);

        $issuedAt = null;
        if (preg_match('/fecha\s+(?:de\s+)?(?:oficio|emisi[oó]n)[^0-9]{0,40}(\d{1,2}[\/-]\d{1,2}[\/-]\d{4})/ui', $text, $match)) {
            $issuedAt = $this->numericDate($match[1]);
        }
        if (! $issuedAt && preg_match('/fecha\s+(?:de\s+)?(?:oficio|emisi[oó]n).{0,40}?(\d{1,2}\s+de\s+[a-záéíóú]+\s+de\s+\d{4})/ui', $text, $match)) {
            $issuedAt = $this->spanishDate($match[1]);
        }

        if (! $issuedAt || ! $status) {
            return null;
        }

        $detectedRfc = preg_match('/\b([A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3})\b/u', Str::upper($text), $rfcMatch)
            ? $rfcMatch[1]
            : strtoupper($expectedRfc);

        return [
            'issued_at' => $issuedAt,
            'rfc' => $detectedRfc,
            'compliance_status' => $status,
            'source_url' => $sourceUrl,
            'validation_method' => 'infonavit_form',
        ];
    }

    private function numericDate(string $value): ?Carbon
    {
        foreach (['d/m/Y', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function spanishDate(string $value): ?Carbon
    {
        $normalized = Str::of($value)->ascii()->lower()->value();
        if (! preg_match('/(\d{1,2})\s+de\s+([a-z]+)\s+de\s+(\d{4})/', $normalized, $match)) {
            return null;
        }

        $months = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
        ];

        return isset($months[$match[2]])
            ? Carbon::create((int) $match[3], $months[$match[2]], (int) $match[1])->startOfDay()
            : null;
    }

    private function normalizeStatus(string $text): ?string
    {
        return match (true) {
            preg_match('/\bsin\s+adeudo\b/ui', $text) === 1 => 'POSITIVA',
            preg_match('/\bpositiv[ao]\b/ui', $text) === 1 => 'POSITIVA',
            preg_match('/\bcon\s+adeudo\b/ui', $text) === 1 => 'NEGATIVA',
            preg_match('/\bnegativ[ao]\b/ui', $text) === 1 => 'NEGATIVA',
            preg_match('/\bsin\s+antecedentes\b/ui', $text) === 1 => 'SIN OPINION',
            default => null,
        };
    }

    private function absoluteUrl(string $baseUrl, string $action): ?string
    {
        if ($action === '') {
            return $baseUrl;
        }
        if (filter_var($action, FILTER_VALIDATE_URL)) {
            return $action;
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);
        if (! is_string($scheme) || ! is_string($host)) {
            return null;
        }

        if (str_starts_with($action, '/')) {
            return "$scheme://$host$action";
        }

        if (str_starts_with($action, '?')) {
            $path = (string) parse_url($baseUrl, PHP_URL_PATH);

            return "$scheme://$host$path$action";
        }

        $path = (string) parse_url($baseUrl, PHP_URL_PATH);

        return "$scheme://$host/".ltrim(dirname($path).'/'.$action, '/');
    }

    private function isAllowedUrl(string $url): bool
    {
        return parse_url($url, PHP_URL_SCHEME) === 'https'
            && parse_url($url, PHP_URL_HOST) === 'portalmx.infonavit.org.mx';
    }

    private function timeoutSeconds(): int
    {
        return max(30, (int) config('services.infonavit.timeout', 120));
    }

    private function requestTimeoutSeconds(): int
    {
        return min(45, $this->timeoutSeconds());
    }

    private function pollSeconds(): int
    {
        return max(1, (int) config('services.infonavit.poll_seconds', 5));
    }
}
