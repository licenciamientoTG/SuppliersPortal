<?php

namespace App\Reports\Support;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Escritura común de los reportes en Excel y CSV. Una columna es [encabezado, fn(fila) => valor, formato|null].
 */
class ReportSheet
{
    public const MONEY = '$#,##0.00';

    public const PERCENT = '0.0%';

    public const DATE = 'dd/mm/yyyy';

    public const DATETIME = 'dd/mm/yyyy hh:mm';

    public const MONTH = 'mmm-yyyy';

    /**
     * Escribe el bloque de título, los encabezados y las filas con formato tipado y filtro automático.
     *
     * @param  callable|null  $isBold  fn(fila) => bool para resaltar subtotales
     */
    public static function write(Worksheet $sheet, string $title, array $header, array $columns, iterable $rows, int $freezeColumn = 1, ?callable $isBold = null): int
    {
        $sheet->setTitle($title);
        foreach ($header as $index => $text) {
            $sheet->setCellValue('A'.($index + 1), $text);
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
        $headingRow = count($header) + 2;
        $sheet->fromArray(array_column($columns, 0), null, 'A'.$headingRow);
        $sheet->getStyle("A{$headingRow}:{$lastColumn}{$headingRow}")->getFont()->setBold(true);

        $first = $headingRow + 1;
        $line = $first;
        foreach ($rows as $row) {
            $sheet->fromArray(array_map(fn ($column) => $column[1]($row), $columns), null, 'A'.$line, true);
            if ($isBold && $isBold($row)) {
                $sheet->getStyle("A{$line}:{$lastColumn}{$line}")->getFont()->setBold(true);
            }
            $line++;
        }
        $last = max($first, $line - 1);

        foreach ($columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            if ($column[2] ?? null) {
                $sheet->getStyle("{$letter}{$first}:{$letter}{$last}")->getNumberFormat()->setFormatCode($column[2]);
            }
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
        $sheet->freezePane(Coordinate::stringFromColumnIndex($freezeColumn).$first);
        $sheet->setAutoFilter("A{$headingRow}:{$lastColumn}{$last}");

        return $line - $first;
    }

    /** CSV UTF-8 con BOM y separador coma. */
    public static function csv(array $columns, Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $columns) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_column($columns, 0));
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($column) => $column[1]($row), $columns));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
