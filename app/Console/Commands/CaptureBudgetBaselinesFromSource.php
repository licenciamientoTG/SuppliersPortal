<?php

namespace App\Console\Commands;

use App\Models\AnnualBudget;
use App\Services\Budget2026DgaImportService;
use App\Services\Budget2026GasomexImportService;
use App\Services\Budget2026SmaImportService;
use App\Services\Budget2026TsaImportService;
use App\Services\BudgetBaselineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CaptureBudgetBaselinesFromSource extends Command
{
    private const COMPANIES = [
        'dga' => [Budget2026DgaImportService::class, 'docs/Presupuesto Anual 2026 DGA.xlsx'],
        'gasomex' => [Budget2026GasomexImportService::class, 'docs/Presupuesto Anual 2026 GASOMEX.xlsx'],
        'sma' => [Budget2026SmaImportService::class, 'docs/Presupuesto Anual 2026 SMA.xlsx'],
        'tsa' => [Budget2026TsaImportService::class, 'docs/Presupuesto Anual 2026 TSA.xlsx'],
    ];

    protected $signature = 'budget:capture-baselines-from-source
        {company : dga, gasomex, sma o tsa}
        {--file= : Ruta del workbook aprobado (por defecto el de docs/)}
        {--year=2026 : Año fiscal}
        {--apply : Guarda la base; sin esta opción solo muestra la vista previa}';

    protected $description = 'Toma el presupuesto original (RP-02) del workbook aprobado para presupuestos APROBADOS que aún no tienen base.';

    public function handle(BudgetBaselineService $baselines): int
    {
        ini_set('memory_limit', '1024M');
        $company = strtolower((string) $this->argument('company'));
        if (! isset(self::COMPANIES[$company])) {
            $this->error('Empresa no válida. Usa: '.implode(', ', array_keys(self::COMPANIES)));

            return self::FAILURE;
        }
        [$analyzerClass, $defaultFile] = self::COMPANIES[$company];
        $file = (string) ($this->option('file') ?: $defaultFile);
        $year = (int) $this->option('year');
        $apply = (bool) $this->option('apply');
        if (! File::exists($file)) {
            $this->error("No existe el archivo: {$file}");

            return self::FAILURE;
        }

        if ($apply) {
            $connection = DB::connection();
            $this->warn(sprintf('Conexión: %s · Base de datos: %s', $connection->getName(), $connection->getDatabaseName()));
        } else {
            $this->info('Vista previa (no se guarda nada). Usa --apply para capturar la base.');
        }

        $report = app($analyzerClass)->analyze($file, $year);
        foreach ($report['missing_cost_centers'] ?? [] as $missing) {
            $this->warn("Hoja {$missing['sheet']}: no se encontró el centro de costo {$missing['cost_center_name']}.");
        }
        if (($report['unmatched_rows'] ?? []) !== []) {
            $this->warn('Filas del workbook sin cédula emparejada (no entran en la base): '.count($report['unmatched_rows']));
        }

        foreach ($report['processed_sheets'] as $sheet) {
            if ($sheet['cost_center_missing'] ?? false) {
                continue;
            }
            $budget = AnnualBudget::query()->where('cost_center_id', $sheet['cost_center_id'])
                ->where('fiscal_year', $year)->where('status', 'APROBADO')->first();
            if (! $budget) {
                $this->line("- {$sheet['cost_center_name']}: sin presupuesto APROBADO {$year}; la base se tomará al aprobarlo.");

                continue;
            }

            $preview = $baselines->previewFromSource($budget, $sheet);
            $totals = $preview['totals'];
            $this->line(sprintf(
                '- %s: original=%0.2f movimientos=%0.2f vigente=%0.2f diferencia=%0.2f diferencias=%d',
                $sheet['cost_center_name'], $totals['original'], $totals['movements'], $totals['current'],
                $totals['difference'], $preview['lines_with_difference'],
            ));

            if (! $apply) {
                continue;
            }
            $result = $baselines->captureFromSource($budget, $sheet, null);
            if ($result['skipped']) {
                $this->line('  ya tiene base; no se modificó.');

                continue;
            }
            $this->info("  base capturada: {$result['captured']} renglones.");
            foreach ($result['unmatched'] as $line) {
                $this->warn(sprintf('  renglón del workbook sin distribución actual (mes %d, cuenta %d, cédula %s): %0.2f',
                    $line['month'], $line['expense_category_id'], $line['budget_cedula_id'] ?? '—', $line['amount']));
            }
        }

        return self::SUCCESS;
    }
}
