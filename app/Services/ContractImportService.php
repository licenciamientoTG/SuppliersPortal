<?php

namespace App\Services;

use App\Enum\ContractType;
use App\Enum\UnitOfMeasure;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ProductService;
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

        $validRows              = [];
        $errorRows              = [];
        $seenContracts          = []; // clave: empresa+rfc+start+end (normalizado)
        $seenProductsPerContract = []; // clave: contractKey+product_id

        foreach ($rows as $i => $row) {
            [$empresaCode, $supplierRfc, $startDate, $endDate, $contractAmount, $productCode, $unitPrice, $currency, $unitOfMeasure, $contractType]
                = array_pad($row, 10, null);

            $lineNum = $i + 2; // +2: header + 1-based
            $errors  = [];

            $company = Company::where('code', $empresaCode)->where('is_active', true)->first();
            if (! $company) {
                $errors[] = "Empresa '{$empresaCode}' no encontrada o inactiva.";
            }

            // Mismo criterio que el alta manual (Supplier::approved()): aprobado y activo
            $supplier = Supplier::approved()->where('rfc', $supplierRfc)->first();
            if (! $supplier) {
                $errors[] = "Proveedor RFC '{$supplierRfc}' no encontrado, no aprobado o inactivo.";
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

            // Fix #4 — normalize dates before building contract key
            $normalizedStart = $startDate ? Carbon::parse($startDate)->toDateString() : $startDate;
            $normalizedEnd   = $endDate   ? Carbon::parse($endDate)->toDateString()   : $endDate;

            $product = ProductService::where('code', $productCode)
                ->active()
                ->first();
            if (! $product) {
                $errors[] = "Producto '{$productCode}' no encontrado o inactivo.";
            }

            if (! is_numeric($unitPrice) || $unitPrice <= 0) {
                $errors[] = "unit_price inválido: '{$unitPrice}'.";
            }

            $uom = strtoupper(trim((string) $unitOfMeasure));
            if ($uom === '') {
                $errors[] = 'unit_of_measure requerida (ej. PZA, KG, L).';
            } elseif (! UnitOfMeasure::tryFrom($uom)) {
                $errors[] = "unit_of_measure inválida: '{$unitOfMeasure}'.";
            }

            // Obligatoria: un convenio importado por omisión como iguala brincaría la autorización
            $type = strtolower(trim((string) $contractType));
            if ($type === '') {
                $errors[] = 'contract_type requerido (iguala o convenio).';
            } elseif (! ContractType::tryFrom($type)) {
                $errors[] = "contract_type inválido: '{$contractType}' (use iguala o convenio).";
            }

            // Fix #4 — use normalized dates in contract key
            $contractKey = "{$empresaCode}|{$supplierRfc}|{$normalizedStart}|{$normalizedEnd}";

            // Fix #5 — in-file duplicate product check
            $productKey = "{$contractKey}|{$product?->id}";
            if ($product && isset($seenProductsPerContract[$productKey])) {
                $errors[] = "Producto '{$productCode}' duplicado para el mismo contrato en este archivo.";
            }

            // Deduplicación en BD
            if (empty($errors) && $company && $supplier) {
                $exists = Contract::where('company_id', $company->id)
                    ->where('supplier_id', $supplier->id)
                    ->whereDate('start_date', $normalizedStart)
                    ->whereDate('end_date', $normalizedEnd)
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
                'start_date'         => $normalizedStart,
                'end_date'           => $normalizedEnd,
                'contract_amount'    => is_numeric($contractAmount) ? $contractAmount : 0,
                'product_service_id' => $product?->id,
                'product_code'       => $productCode,
                'unit_price'         => $unitPrice,
                'currency_code'      => $currency ?: 'MXN',
                'unit_of_measure'    => $uom,
                'contract_type'      => $type,
                'contract_key'       => $contractKey,
            ];

            if ($errors) {
                $parsedRow['errors'] = $errors;
                $errorRows[] = $parsedRow;
            } else {
                $seenContracts[$contractKey]          = true;
                $seenProductsPerContract[$productKey] = true;
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

        // Fix #6 — resolve once outside the loop
        $userId = Auth::id();
        $user   = Auth::user();

        DB::transaction(function () use ($grouped, &$count, $userId, $user) {
            foreach ($grouped as $key => $rows) {
                $first   = $rows->first();
                $folio   = Contract::nextFolio();

                $contract = Contract::create([
                    'folio'           => $folio,
                    'company_id'      => $first['company_id'],
                    'supplier_id'     => $first['supplier_id'],
                    'contract_type'   => $first['contract_type'] ?? 'iguala',
                    'start_date'      => $first['start_date'],
                    'end_date'        => $first['end_date'],
                    'contract_amount' => $first['contract_amount'],
                    'status'          => 'active',
                    'created_by'      => $userId,
                    'updated_by'      => $userId,
                ]);

                foreach ($rows as $row) {
                    $contract->products()->create([
                        'product_service_id' => $row['product_service_id'],
                        'unit_price'         => $row['unit_price'],
                        'currency_code'      => $row['currency_code'],
                        'unit_of_measure'    => $row['unit_of_measure'],
                    ]);
                }

                activity('contracts')
                    ->causedBy($user)
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
