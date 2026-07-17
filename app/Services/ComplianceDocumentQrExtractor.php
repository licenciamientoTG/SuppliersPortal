<?php

namespace App\Services;

use App\Contracts\DocumentIssueDateExtractor;
use App\Models\Supplier;
use App\Models\SupplierDocumentType;
use App\ValueObjects\DocumentIssueDateExtraction;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ComplianceDocumentQrExtractor implements DocumentIssueDateExtractor
{
    private const CODES = ['constancia_fiscal', 'opinion_sat', 'opinion_imss', 'opinion_infonavit'];

    public function __construct(private readonly DocumentQrReaderService $qrReader) {}

    public function supports(SupplierDocumentType $type): bool
    {
        return in_array($type->code, self::CODES, true);
    }

    public function extract(UploadedFile $file, SupplierDocumentType $type, ?Supplier $supplier = null): ?DocumentIssueDateExtraction
    {
        $payloads = $this->qrReader->read($file);
        $metadata = [
            'status' => 'unavailable',
            'document_code' => $type->code,
            'qr_found' => $payloads !== [],
            'qr_payloads' => array_map(fn ($payload) => $this->safePayload($payload), $payloads),
        ];

        foreach ($payloads as $payload) {
            $result = match ($type->code) {
                'constancia_fiscal' => $this->fromCsfQr($payload),
                'opinion_sat' => $this->fromSatOpinionQr($payload),
                'opinion_infonavit' => null,
                'opinion_imss' => $this->fromImssQr($payload),
                default => null,
            };

            if ($result) {
                $issuedAt = $result['issued_at'];

                return new DocumentIssueDateExtraction($issuedAt, array_merge($metadata, $result, [
                    'issued_at' => $issuedAt->toDateString(),
                    'status' => 'extracted',
                ]));
            }
        }

        if ($type->code === 'opinion_infonavit' && $payloads !== []) {
            $metadata['status'] = 'pending_external_validation';
            $metadata['message'] = 'El QR fue localizado. INFONAVIT no respondio; Compras puede reintentar la consulta.';
        }

        return new DocumentIssueDateExtraction(null, $metadata);
    }

    private function fromCsfQr(string $payload): ?array
    {
        parse_str((string) parse_url($payload, PHP_URL_QUERY), $query);
        $data = urldecode((string) ($query['D3'] ?? ''));
        if (! str_contains($payload, 'siat.sat.gob.mx') || ! preg_match('/\|\|(\d{4})\/(\d{2})\/(\d{2})\|([A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3})\|CONSTANCIA DE SITUACI/i', $data, $match)) {
            return null;
        }

        return ['issued_at' => Carbon::createFromFormat('Y-m-d', "$match[1]-$match[2]-$match[3]"), 'rfc' => strtoupper($match[4]), 'compliance_status' => null, 'source_url' => $payload];
    }

    private function fromSatOpinionQr(string $payload): ?array
    {
        parse_str((string) parse_url($payload, PHP_URL_QUERY), $query);
        $parts = explode('_', urldecode((string) ($query['D3'] ?? '')));
        if (! str_contains($payload, 'siat.sat.gob.mx') || count($parts) < 4 || ! preg_match('/^\d{2}-\d{2}-\d{4}$/', $parts[2])) {
            return null;
        }

        $opinion = strtoupper($parts[3]) === 'P' ? 'POSITIVA' : 'NEGATIVA';

        return ['issued_at' => Carbon::createFromFormat('d-m-Y', $parts[2]), 'rfc' => strtoupper($parts[1]), 'compliance_status' => $opinion, 'source_url' => $payload];
    }

    private function fromImssQr(string $payload): ?array
    {
        if (! str_contains($payload, 'Invocante:portalimssdigital')) {
            return null;
        }

        $fields = [];
        foreach (array_filter(explode('|', trim($payload, '|'))) as $part) {
            if (! str_contains($part, ':')) {
                continue;
            }
            [$label, $value] = explode(':', $part, 2);
            $fields[Str::of($label)->ascii()->lower()->trim()->value()] = trim($value);
        }

        $issuedAt = $this->spanishDate($fields['fecha'] ?? '');
        $rfc = strtoupper($fields['rfc'] ?? '');
        $opinion = Str::of($fields['opinion'] ?? '')->ascii()->upper()->trim()->value();

        if (! $issuedAt || ! preg_match('/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/u', $rfc) || $opinion === '') {
            return null;
        }

        return [
            'issued_at' => $issuedAt,
            'rfc' => $rfc,
            'compliance_status' => $opinion,
            'folio' => $fields['folio'] ?? null,
            'source_url' => null,
            'validation_method' => 'imss_qr_payload',
        ];
    }

    private function spanishDate(string $value): ?Carbon
    {
        $normalized = Str::of($value)->ascii()->lower()->value();
        if (! preg_match('/(\d{1,2})\s+de\s+([a-z]+)(?:\s+de)?\s+(\d{4})/', $normalized, $match)) {
            return null;
        }

        $months = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
        ];
        $month = $months[$match[2]] ?? null;

        return $month ? Carbon::create((int) $match[3], $month, (int) $match[1])->startOfDay() : null;
    }

    private function safePayload(string $payload): string
    {
        if (parse_url($payload, PHP_URL_HOST) === 'portalmx.infonavit.org.mx') {
            return $payload;
        }

        return strlen($payload) > 2000 ? substr($payload, 0, 2000).'...' : $payload;
    }
}
