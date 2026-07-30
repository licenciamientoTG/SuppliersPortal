<?php

namespace App\Services;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\CostCenter;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Budget2026DgaImportService
{
    private const MONTH_COLUMN_MAP = [
        'Y' => 1,
        'Z' => 2,
        'AA' => 3,
        'AB' => 4,
        'AC' => 5,
        'AD' => 6,
        'AE' => 7,
        'AF' => 8,
        'AG' => 9,
        'AH' => 10,
        'AI' => 11,
        'AJ' => 12,
    ];

    protected function configKey(): string
    {
        return 'budget_2026_dga';
    }

    protected function configValue(string $path, mixed $default = null): mixed
    {
        return config($this->configKey() . '.' . $path, $default);
    }

    public function analyze(string $filePath, int $year = 2026): array
    {
        $sheetMap = collect($this->configValue('sheet_to_cost_center', []));
        $ignored = collect($this->configValue('ignored_sheets', []));
        $availableSheets = collect(IOFactory::createReader('Xlsx')->listWorksheetNames($filePath));

        $costCenters = CostCenter::query()
            ->with('company')
            ->whereIn('name', $sheetMap->values()->all())
            ->get();

        $cedulasByCategory = BudgetCedula::query()
            ->with('expenseCategory')
            ->active()
            ->notDeleted()
            ->get()
            ->groupBy(fn (BudgetCedula $cedula) => $cedula->expenseCategory?->code);

        $report = [
            'file' => $filePath,
            'year' => $year,
            'processed_sheets' => [],
            'ignored_sheets' => [],
            'missing_cost_centers' => [],
            'unmatched_rows' => [],
            'matched_rows' => 0,
            'matched_monthly_values' => 0,
        ];

        foreach ($availableSheets as $sheetName) {
            if ($ignored->contains($sheetName)) {
                $report['ignored_sheets'][] = $sheetName;
                continue;
            }

            if (! $sheetMap->has($sheetName)) {
                $report['ignored_sheets'][] = $sheetName;
                continue;
            }

            $targetCostCenterName = $sheetMap->get($sheetName);
            $targetCompanyName = $this->configValue("sheet_to_company.{$sheetName}");
            $costCenter = $this->resolveTargetCostCenter($costCenters, $targetCostCenterName, $targetCompanyName);
            if (! $costCenter) {
                $report['missing_cost_centers'][] = [
                    'sheet' => $sheetName,
                    'cost_center_name' => $targetCostCenterName,
                    'company_name' => $targetCompanyName,
                ];
                continue;
            }

            $spreadsheet = $this->loadWorkbook($filePath, [$sheetName]);
                $sheetReport = $this->analyzeSheet(
                $spreadsheet->getSheetByName($sheetName),
                $sheetName,
                $costCenter,
                $cedulasByCategory
            );
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $report['processed_sheets'][] = $sheetReport;
            $report['matched_rows'] += $sheetReport['matched_rows'];
            $report['matched_monthly_values'] += $sheetReport['matched_monthly_values'];
            $report['unmatched_rows'] = array_merge($report['unmatched_rows'], $sheetReport['unmatched_rows']);
        }

        return $report;
    }

    public function import(string $filePath, int $year = 2026, string $status = 'PLANIFICACION'): array
    {
        $analysis = $this->analyze($filePath, $year);
        $actorId = Auth::id() ?: User::query()->value('id');

        if (! $actorId) {
            throw new RuntimeException('No existe un usuario para registrar la importación presupuestal.');
        }

        DB::transaction(function () use ($analysis, $year, $status, $actorId) {
            foreach ($analysis['processed_sheets'] as $sheetReport) {
                if ($sheetReport['cost_center_missing']) {
                    continue;
                }

                if (empty($sheetReport['rows'])) {
                    continue;
                }

                $budget = AnnualBudget::query()
                    ->withTrashed()
                    ->firstOrNew([
                        'cost_center_id' => $sheetReport['cost_center_id'],
                        'fiscal_year' => $year,
                    ]);

                if ($budget->trashed()) {
                    $budget->restore();
                }

                $budget->fill([
                    'total_annual_amount' => $sheetReport['annual_total'],
                    'status' => $budget->exists ? $budget->status : $status,
                    'created_by' => $budget->created_by ?: $actorId,
                    'updated_by' => $actorId,
                ]);
                $budget->save();

                $existingDistributions = $budget->monthlyDistributions()->get();
                if ($existingDistributions->contains(fn (BudgetMonthlyDistribution $distribution) => (float) $distribution->consumed_amount > 0 || (float) $distribution->committed_amount > 0)) {
                    throw new RuntimeException("El presupuesto {$budget->id} ya tiene consumo o compromiso y no se puede reemplazar automáticamente.");
                }

                $budget->monthlyDistributions()->forceDelete();

                $aggregatedRows = [];
                foreach ($sheetReport['rows'] as $row) {
                    foreach ($row['months'] as $month => $amount) {
                        if (abs((float) $amount) < 0.000001) {
                            continue;
                        }

                        $cedulaId = $row['budget_cedula_id'];
                        $monthInt = (int) $month;
                        $key = "{$cedulaId}_{$monthInt}";

                        if (! isset($aggregatedRows[$key])) {
                            $aggregatedRows[$key] = [
                                'annual_budget_id' => $budget->id,
                                'budget_cedula_id' => $cedulaId,
                                'expense_category_id' => $row['expense_category_id'],
                                'month' => $monthInt,
                                'assigned_amount' => 0,
                                'consumed_amount' => 0,
                                'committed_amount' => 0,
                                'created_by' => $actorId,
                                'updated_by' => $actorId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        $aggregatedRows[$key]['assigned_amount'] += $amount;
                    }
                }

                if ($aggregatedRows !== []) {
                    $chunks = array_chunk(array_values($aggregatedRows), 100);
                    foreach ($chunks as $chunk) {
                        BudgetMonthlyDistribution::query()->insert($chunk);
                    }
                }
            }
        });

        return $analysis;
    }

    private function analyzeSheet($sheet, string $sheetName, CostCenter $costCenter, Collection $cedulasByCategory): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $sectionCode = null;
        $rows = [];
        $unmatchedRows = [];
        $matchedRows = 0;
        $matchedMonthlyValues = 0;
        $processedHashes = [];

        for ($row = 1; $row <= $highestRow; $row++) {
            $sectionCode = $this->resolveSectionCode($sheet, $row, $sectionCode);
            if (! $sectionCode) {
                continue;
            }

            $months = $this->extract2026Months($sheet, $row);
            if ($months === [] || collect($months)->every(fn ($value) => abs((float) $value) < 0.000001)) {
                continue;
            }

            $rowCandidates = $this->extractRowCandidates($sheet, $row);
            if ($rowCandidates === []) {
                continue;
            }

            if ($this->shouldSkipRow($sectionCode, $rowCandidates)) {
                continue;
            }

            $cedula = $this->matchCedula($sectionCode, $rowCandidates, $cedulasByCategory);
            if (! $cedula) {
                $unmatchedRows[] = [
                    'sheet' => $sheetName,
                    'row' => $row,
                    'category_code' => $sectionCode,
                    'candidates' => $rowCandidates,
                    'annual_total' => array_sum($months),
                ];
                continue;
            }

            // Deduplicación de filas idénticas en la misma hoja
            $rowHash = md5(json_encode([
                'c' => $cedula->id,
                'n' => $rowCandidates[0] ?? '',
                'm' => $months,
            ]));

            if (isset($processedHashes[$rowHash])) {
                continue;
            }
            $processedHashes[$rowHash] = true;

            $rows[] = [
                'row' => $row,
                'category_code' => $sectionCode,
                'expense_category_id' => $cedula->expense_category_id,
                'budget_cedula_id' => $cedula->id,
                'budget_cedula_name' => $cedula->name,
                'months' => $months,
                'annual_total' => array_sum($months),
            ];

            $matchedRows++;
            $matchedMonthlyValues += count(array_filter($months, fn ($value) => abs((float) $value) >= 0.000001));
        }

        return [
            'sheet' => $sheetName,
            'cost_center_id' => $costCenter->id,
            'cost_center_name' => $costCenter->name,
            'cost_center_missing' => false,
            'matched_rows' => $matchedRows,
            'matched_monthly_values' => $matchedMonthlyValues,
            'annual_total' => array_sum(array_column($rows, 'annual_total')),
            'rows' => $rows,
            'unmatched_rows' => $unmatchedRows,
        ];
    }

    private function loadWorkbook(string $filePath, ?array $sheetNames = null)
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        if ($sheetNames) {
            $reader->setLoadSheetsOnly($sheetNames);
        }
        $reader->setReadFilter(new class implements IReadFilter
        {
            private array $columns = [
                'A',
                'B',
                'C',
                'D',
                'E',
                'I',
                'Y',
                'Z',
                'AA',
                'AB',
                'AC',
                'AD',
                'AE',
                'AF',
                'AG',
                'AH',
                'AI',
                'AJ',
                'AK',
            ];

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return in_array($columnAddress, $this->columns, true);
            }
        });

        return $reader->load($filePath);
    }

    private function resolveSectionCode($sheet, int $row, ?string $currentSectionCode): ?string
    {
        foreach (['A', 'I'] as $column) {
            $value = $this->stringCell($sheet, $column . $row);
            if (! $value) {
                continue;
            }

            $normalized = $this->normalize($value);
            if (preg_match('/^([a-z])\s+(.+)$/', $normalized, $matches)) {
                $code = strtoupper($matches[1]);
                $label = $matches[2];

                $alias = $this->configValue("section_aliases.{$label}");
                if ($alias) {
                    return $alias;
                }

                if ($this->hasCategoryCode($code)) {
                    return $code;
                }
            }

            $alias = $this->configValue("section_aliases.{$normalized}");
            if ($alias) {
                return $alias;
            }
        }

        return $currentSectionCode;
    }

    private function extract2026Months($sheet, int $row): array
    {
        $months = [];

        foreach (self::MONTH_COLUMN_MAP as $column => $month) {
            $value = $sheet->getCell($column . $row)->getCalculatedValue();
            if ($value === null || $value === '' || $value === '-') {
                continue;
            }

            if (! is_numeric($value)) {
                continue;
            }

            $months[$month] = round((float) $value, 2);
        }

        return $months;
    }

    private function extractRowCandidates($sheet, int $row): array
    {
        $candidates = collect([
            $this->stringCell($sheet, 'I' . $row),
            $this->stringCell($sheet, 'E' . $row),
            $this->stringCell($sheet, 'C' . $row),
            $this->stringCell($sheet, 'D' . $row),
        ])
            ->filter()
            ->values()
            ->all();

        return array_values(array_unique($candidates));
    }

    private function matchCedula(string $categoryCode, array $rowCandidates, Collection $cedulasByCategory): ?BudgetCedula
    {
        /** @var Collection<int, BudgetCedula> $catalog */
        $catalog = $cedulasByCategory->get($categoryCode, collect());
        $matched = $this->matchCedulaInCatalog($categoryCode, $rowCandidates, $catalog);
        if ($matched) {
            return $matched;
        }

        foreach ($cedulasByCategory as $otherCategoryCode => $otherCatalog) {
            if ($otherCategoryCode === $categoryCode) {
                continue;
            }

            $matched = $this->matchCedulaInCatalog((string) $otherCategoryCode, $rowCandidates, $otherCatalog);
            if ($matched) {
                return $matched;
            }
        }

        return null;
    }

    private function matchCedulaInCatalog(string $categoryCode, array $rowCandidates, Collection $catalog): ?BudgetCedula
    {
        if ($catalog->isEmpty()) {
            return null;
        }

        $normalizedCatalog = $catalog->mapWithKeys(function (BudgetCedula $cedula) {
            return [$this->normalize($cedula->name) => $cedula];
        });

        foreach ($rowCandidates as $candidate) {
            $normalized = $this->normalize($candidate);
            if ($normalized === '') {
                continue;
            }

            if ($normalizedCatalog->has($normalized)) {
                return $normalizedCatalog->get($normalized);
            }

            $aliasTarget = $this->configValue("cedula_aliases.{$categoryCode}.{$normalized}");
            if ($aliasTarget) {
                $matched = $normalizedCatalog->get($this->normalize($aliasTarget));
                if ($matched) {
                    return $matched;
                }
            }

            $legacyAliasTarget = $this->legacyCedulaAlias($categoryCode, $normalized);
            if ($legacyAliasTarget) {
                $matched = $normalizedCatalog->get($this->normalize($legacyAliasTarget));
                if ($matched) {
                    return $matched;
                }
            }
        }

        return null;
    }

    private function stringCell($sheet, string $cell): ?string
    {
        $value = $sheet->getCell($cell)->getCalculatedValue();
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value));

        return $value === '' ? null : $value;
    }

    private function normalize(string $value): string
    {
        $ascii = Str::ascii($value);
        $ascii = strtolower($ascii);
        $ascii = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? '';

        return trim(preg_replace('/\s+/', ' ', $ascii) ?? '');
    }

    private function hasCategoryCode(string $code): bool
    {
        return in_array($code, ['A', 'B', 'C', 'D', 'E', 'F', 'H', 'I', 'J', 'K', 'L', 'M'], true);
    }

    private function resolveTargetCostCenter(Collection $costCenters, string $name, ?string $companyName): ?CostCenter
    {
        $normalizedName = $this->normalize($name);
        $matches = $costCenters
            ->filter(fn (CostCenter $costCenter) => $this->normalize($costCenter->name) === $normalizedName)
            ->values();

        if ($matches->isEmpty() && $normalizedName === 'c independencia') {
            $matches = $costCenters
                ->filter(fn (CostCenter $costCenter) => $this->normalize($costCenter->name) === $this->normalize('C. Independencia'))
                ->values();
        }

        if ($matches->isEmpty()) {
            $matches = $costCenters
                ->filter(fn (CostCenter $costCenter) => str_contains($this->normalize($costCenter->name), $normalizedName) || str_contains($normalizedName, $this->normalize($costCenter->name)))
                ->values();
        }

        if ($matches->isEmpty()) {
            return null;
        }

        if ($companyName) {
            $normalizedCompany = $this->normalize($companyName);
            $companyMatch = $matches->first(
                fn (CostCenter $costCenter) => $this->normalize((string) $costCenter->company?->name) === $normalizedCompany
            );
            if ($companyMatch) {
                return $companyMatch;
            }
        }

        $diazMatch = $matches->first(
            fn (CostCenter $costCenter) => $this->normalize((string) $costCenter->company?->name) === $this->normalize('Diaz Gas')
        );
        if ($diazMatch) {
            return $diazMatch;
        }

        return $matches->sortBy('id')->first();
    }

    private function shouldSkipRow(string $categoryCode, array $rowCandidates): bool
    {
        $global = collect($this->configValue('skip_candidates.global', []))
            ->map(fn (string $value) => $this->normalize($value));
        $categorySpecific = collect($this->configValue("skip_candidates.{$categoryCode}", []))
            ->map(fn (string $value) => $this->normalize($value));

        foreach ($rowCandidates as $candidate) {
            $normalized = $this->normalize($candidate);
            if ($normalized === '') {
                continue;
            }

            if ($categoryCode !== 'A' && in_array($normalized, ['maxima', 'super', 'diesel'], true)) {
                return true;
            }

            if ($this->matchesSkipPattern($normalized, $global) || $this->matchesSkipPattern($normalized, $categorySpecific)) {
                return true;
            }

            if (str_starts_with($normalized, 'total ') || str_starts_with($normalized, 'utilidad ')) {
                return true;
            }
        }

        return false;
    }

    private function matchesSkipPattern(string $candidate, Collection $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === '') {
                continue;
            }

            if ($candidate === $pattern || str_contains($candidate, $pattern) || str_contains($pattern, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function legacyCedulaAlias(string $categoryCode, string $normalizedCandidate): ?string
    {
        $legacy = [
            'E' => [
                'responsabilidad social' => 'Eventos',
                'mantenimiento de cartera' => 'Servicios Administrativos',
                'cuotas y suscripciones' => 'Otros Gastos',
                'honorarios fiscales p m' => 'Honorarios Administrativos P.M.',
                'honorarios fiscales p f' => 'Honorarios Administrativos P.F.',
            ],
        ];

        return $legacy[$categoryCode][$normalizedCandidate] ?? null;
    }
}
