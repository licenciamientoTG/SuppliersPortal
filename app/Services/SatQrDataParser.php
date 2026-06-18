<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;

class SatQrDataParser
{
    public function parse(string $html, string $sourceUrl): array
    {
        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $sections = [];
        $flatItems = [];

        foreach ($xpath->query('//ul[li[@data-role="list-divider"]]') as $list) {
            if (! $list instanceof DOMElement) {
                continue;
            }

            $titleNode = $xpath->query('./li[@data-role="list-divider"][1]', $list)?->item(0);
            $title = $titleNode ? $this->cleanText($titleNode->textContent) : 'Seccion';
            $items = [];

            foreach ($xpath->query('.//tbody/tr', $list) as $row) {
                if (! $row instanceof DOMElement) {
                    continue;
                }

                $cells = $xpath->query('./td', $row);

                if ($cells->length < 2) {
                    continue;
                }

                $label = $this->normalizeLabel($cells->item(0)?->textContent ?? '');
                $value = $this->cleanText($cells->item(1)?->textContent ?? '');

                if ($label === '') {
                    continue;
                }

                $items[] = [
                    'label' => $label,
                    'value' => $value,
                ];

                $flatItems[$label][] = $value;
            }

            if ($items !== []) {
                $sections[] = [
                    'title' => $title,
                    'items' => $items,
                ];
            }
        }

        return [
            'source_url' => $sourceUrl,
            'rfc' => $this->extractRfc($html, $sourceUrl),
            'summary' => [
                'nombre_completo' => $this->firstAvailable($flatItems, [
                    'Denominacion/Razon Social',
                ], $this->buildPhysicalPersonName($flatItems) ?? $this->firstAvailable($flatItems, [
                    'Nombre',
                ])),
                'curp' => $this->firstAvailable($flatItems, ['CURP']),
                'situacion' => $this->firstAvailable($flatItems, ['Situacion del contribuyente']),
                'cp' => $this->firstAvailable($flatItems, ['CP']),
                'entidad' => $this->firstAvailable($flatItems, ['Entidad Federativa']),
                'municipio' => $this->firstAvailable($flatItems, ['Municipio o delegacion']),
            ],
            'items_by_label' => $flatItems,
            'sections' => $sections,
        ];
    }

    private function extractRfc(string $html, string $sourceUrl): ?string
    {
        if (preg_match('/El RFC:\s*([A-Z0-9&Ñ]{12,13})/u', $html, $matches) === 1) {
            return $matches[1];
        }

        parse_str((string) parse_url($sourceUrl, PHP_URL_QUERY), $query);

        if (! empty($query['D3']) && is_string($query['D3'])) {
            $parts = explode('_', $query['D3']);

            return end($parts) ?: null;
        }

        return null;
    }

    private function buildPhysicalPersonName(array $flatItems): ?string
    {
        $parts = array_filter([
            $this->firstAvailable($flatItems, ['Nombre']),
            $this->firstAvailable($flatItems, ['Apellido Paterno']),
            $this->firstAvailable($flatItems, ['Apellido Materno']),
        ]);

        if ($parts === []) {
            return null;
        }

        return implode(' ', $parts);
    }

    private function firstAvailable(array $flatItems, array $labels, ?string $fallback = null): ?string
    {
        foreach ($labels as $label) {
            if (! empty($flatItems[$label][0])) {
                return $flatItems[$label][0];
            }
        }

        return $fallback;
    }

    private function normalizeLabel(string $label): string
    {
        $label = $this->cleanText($label);

        return rtrim($label, ':');
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
