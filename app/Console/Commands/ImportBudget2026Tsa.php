<?php

namespace App\Console\Commands;

use App\Services\Budget2026TsaImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportBudget2026Tsa extends Command
{
    protected $signature = 'budgets:import-2026-tsa
        {--file=docs/Presupuesto Anual 2026 TSA.xlsx : Ruta del workbook}
        {--year=2026 : Año fiscal destino}
        {--status=PLANIFICACION : Estatus para presupuestos nuevos}
        {--dry-run : Solo analizar sin persistir}';

    protected $description = 'Analiza o importa el presupuesto anual 2026 de TSA desde el workbook aprobado.';

    public function __construct(
        private readonly Budget2026TsaImportService $importService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $file = (string) $this->option('file');
        $year = (int) $this->option('year');
        $status = (string) $this->option('status');
        $dryRun = (bool) $this->option('dry-run');

        if (! File::exists($file)) {
            $this->error("No existe el archivo: {$file}");
            return self::FAILURE;
        }

        try {
            $report = $dryRun
                ? $this->importService->analyze($file, $year)
                : $this->importService->import($file, $year, $status);

            $this->info($dryRun ? 'Análisis completado.' : 'Importación completada.');
            $this->line('Hojas procesadas: ' . count($report['processed_sheets']));
            $this->line('Hojas ignoradas: ' . count($report['ignored_sheets']));
            $this->line('Filas emparejadas: ' . $report['matched_rows']);
            $this->line('Valores mensuales cargables: ' . $report['matched_monthly_values']);
            $this->line('Centros faltantes: ' . count($report['missing_cost_centers']));
            $this->line('Filas no emparejadas: ' . count($report['unmatched_rows']));

            foreach ($report['processed_sheets'] as $sheetReport) {
                $this->line(sprintf(
                    '- %s -> %s | filas=%d | total=%0.2f',
                    $sheetReport['sheet'],
                    $sheetReport['cost_center_name'],
                    $sheetReport['matched_rows'],
                    $sheetReport['annual_total']
                ));
            }

            if ($report['missing_cost_centers'] !== []) {
                $this->warn('Centros de costo no encontrados:');
                foreach ($report['missing_cost_centers'] as $missing) {
                    $this->line("- {$missing['sheet']} -> {$missing['cost_center_name']}");
                }
            }

            if ($report['unmatched_rows'] !== []) {
                $this->warn('Primeras filas no emparejadas:');
                foreach (array_slice($report['unmatched_rows'], 0, 20) as $row) {
                    $this->line(sprintf(
                        '- %s fila %d [%s] %s',
                        $row['sheet'],
                        $row['row'],
                        $row['category_code'],
                        implode(' | ', $row['candidates'])
                    ));
                }
            }

            $outputDir = storage_path('app/reports');
            File::ensureDirectoryExists($outputDir);
            $outputFile = $outputDir . '/budget-import-2026-tsa-' . now()->format('Ymd-His') . '.json';
            File::put($outputFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line('Reporte: ' . $outputFile);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
