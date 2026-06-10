<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ContractImportService
{
    private const MAX_ROWS = 500;

    /**
     * Parsea el archivo, valida fila a fila y devuelve el resultado de la preview.
     * No guarda nada en BD.
     */
    public function preview(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        // Quitar encabezado
        array_shift($rows);

        if (count($rows) > self::MAX_ROWS) {
            return [
                'error'  => 'El archivo supera el límite de ' . self::MAX_ROWS . ' filas.',
                'valid'  => [],
                'errors' => [],
            ];
        }

        $validRows     = [];
        $errorRows     = [];
        $seenContracts = []; // clave: empresa+rfc+start+end

        foreach ($rows as $i => $row) {
            [$empresaCode, $supplierRfc, $startDate, $endDate, $contractAmount, $productCode, $unitPrice, $currency]
                = array_pad($row, 8, null);

            $lineNum = $i + 2; // +2: header + 1-based
            $errors  = [];

            $company = Company::where('code', $empresaCode)->where('is_active', true)->first();
            if (! $company) {
                $errors[] = "Empresa '{$empresaCode}' no encontrada o inactiva.";
            }

            $supplier = Supplier::where('rfc', $supplierRfc)->where('status', 'activo')->first();
            if (! $supplier) {
                $errors[] = "Proveedor RFC '{$supplierRfc}' no encontrado o inactivo.";
            }

            if (! $startDate || ! strtotime($startDate)) {
                $errors[] = "start_date inválida: '{$startDate}'.";
            }
            if (! $endDate || ! strtotime($endDate)) {
                $errors[] = "end_date inválida: '{$endDate}'.";
            }
            if ($startDate && $endDate && strtotime($endDate) <= strtotime($startDate)) {
                $errors[] = "end_date debe ser posterior a start_date.";
            }

            $product = \App\Models\ProductService::where('code', $productCode)
                ->where('is_active', true)
                ->first();
            if (! $product) {
                $errors[] = "Producto '{$productCode}' no encontrado o inactivo.";
            }

            if (! is_numeric($unitPrice) || $unitPrice <= 0) {
                $errors[] = "unit_price inválido: '{$unitPrice}'.";
            }

            // Deduplicación en archivo
            $contractKey = "{$empresaCode}|{$supplierRfc}|{$startDate}|{$endDate}";
            if (isset($seenContracts[$contractKey]) && empty($errors)) {
                // mismo contrato en múltiples filas → agrupar (correcto)
            }

            // Deduplicación en BD
            if (empty($errors) && $company && $supplier) {
                $exists = Contract::where('company_id', $company->id)
                    ->where('supplier_id', $supplier->id)
                    ->whereDate('start_date', Carbon::parse($startDate)->toDateString())
                    ->whereDate('end_date', Carbon::parse($endDate)->toDateString())
                    ->exists();
                if ($exists) {
                    $errors[] = "Ya existe un contrato para empresa+proveedor+fechas en BD.";
                }
            }

            $parsedRow = [
                'line'               => $lineNum,
                'empresa_code'       => $empresaCode,
                'supplier_rfc'       => $supplierRfc,
                'company_id'         => $company?->id,
                'supplier_id'        => $supplier?->id,
                'start_date'         => $startDate,
                'end_date'           => $endDate,
                'contract_amount'    => is_numeric($contractAmount) ? $contractAmount : 0,
                'product_service_id' => $product?->id,
                'product_code'       => $productCode,
                'unit_price'         => $unitPrice,
                'currency_code'      => $currency ?: 'MXN',
                'contract_key'       => $contractKey,
            ];

            if ($errors) {
                $parsedRow['errors'] = $errors;
                $errorRows[] = $parsedRow;
            } else {
                $seenContracts[$contractKey] = true;
                $validRows[] = $parsedRow;
            }
        }

        return [
            'valid'  => $validRows,
            'errors' => $errorRows,
        ];
    }

    /**
     * Recibe las filas válidas (ya procesadas por preview) y las persiste.
     */
    public function confirm(array $validRows): int
    {
        $grouped = collect($validRows)->groupBy('contract_key');
        $count   = 0;

        DB::transaction(function () use ($grouped, &$count) {
            foreach ($grouped as $key => $rows) {
                $first   = $rows->first();
                $folio   = Contract::nextFolio();

                $contract = Contract::create([
                    'folio'           => $folio,
                    'company_id'      => $first['company_id'],
                    'supplier_id'     => $first['supplier_id'],
                    'start_date'      => $first['start_date'],
                    'end_date'        => $first['end_date'],
                    'contract_amount' => $first['contract_amount'],
                    'status'          => 'active',
                    'created_by'      => Auth::id(),
                    'updated_by'      => Auth::id(),
                ]);

                foreach ($rows as $row) {
                    $contract->products()->create([
                        'product_service_id' => $row['product_service_id'],
                        'unit_price'         => $row['unit_price'],
                        'currency_code'      => $row['currency_code'],
                        'unit_of_measure'    => 'PZA', // el CSV no tiene U/M; ajustar layout si se requiere
                    ]);
                }

                activity('contracts')
                    ->causedBy(Auth::user())
                    ->performedOn($contract)
                    ->event('bulk_imported')
                    ->withProperties([
                        'created' => 1,
                        'rows'    => $rows->count(),
                        'source'  => 'csv_import',
                    ])
                    ->log('Importación masiva');

                $count++;
            }
        });

        return $count;
    }
}
