<?php

namespace Database\Seeders;

use App\Enum\ProductServiceStatus;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductServiceSycSeeder extends Seeder
{
    private const SOURCE_PATH = 'storage/app/tmp/Productos_syc.csv';

    /**
     * @var array<string, string>
     */
    private const UNIT_MAP = [
        'PZA' => 'PIEZA',
        'LT' => 'LITRO',
        'CAJA' => 'CAJA',
        'PAQ' => 'PAQUETE',
        'METRO' => 'METRO',
        'SVR' => 'SERVICIO',
    ];

    public function run(): void
    {
        $path = base_path(self::SOURCE_PATH);
        if (! is_file($path)) {
            $this->command?->warn('No se encontro el archivo Productos_syc.csv. Seeder omitido.');

            return;
        }

        $user = User::query()->where('name', 'Super Administrador')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $user) {
            throw new \RuntimeException('No hay usuarios disponibles para registrar la auditoria del seeder SYC.');
        }

        [$headers, $rows] = $this->readCsv($path);
        $expectedHeaders = ['empresa', 'des', 'udm_inv', 'clas1_nombre'];
        if ($headers !== $expectedHeaders) {
            throw new \RuntimeException(
                'Encabezados invalidos en Productos_syc.csv. Se esperaba: '.implode(', ', $expectedHeaders)
            );
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $user, &$created, &$updated): void {
            foreach ($rows as $index => $row) {
                $lineNumber = $index + 2;

                $sourceCompany = $this->normalizeText((string) ($row['empresa'] ?? ''));
                if ($sourceCompany !== '1G_TGS_SERVICIOSYC') {
                    throw new \RuntimeException("Fila {$lineNumber}: la empresa origen debe ser 1G_TGS_SERVICIOSYC.");
                }

                $shortName = $this->normalizeText((string) ($row['des'] ?? ''));
                if ($shortName === '') {
                    throw new \RuntimeException("Fila {$lineNumber}: la columna des es obligatoria.");
                }

                $rawUnit = strtoupper($this->normalizeText((string) ($row['udm_inv'] ?? '')));
                $unitOfMeasure = self::UNIT_MAP[$rawUnit] ?? null;
                if (! $unitOfMeasure) {
                    throw new \RuntimeException("Fila {$lineNumber}: unidad no soportada [{$rawUnit}].");
                }

                $product = ProductService::withTrashed()
                    ->where('short_name', $shortName)
                    ->first();

                if ($product) {
                    if ($product->trashed()) {
                        $product->restore();
                    }

                    $product->fill([
                        'unit_of_measure' => $unitOfMeasure,
                        'status' => ProductServiceStatus::ACTIVE->value,
                        'is_active' => true,
                        'deleted_by' => null,
                        'updated_by' => $user->id,
                    ]);
                    $product->save();
                    $updated++;

                    continue;
                }

                ProductService::create([
                    'code' => ProductService::nextCode(),
                    'technical_description' => null,
                    'short_name' => $shortName,
                    'product_type' => 'PRODUCTO',
                    'unit_of_measure' => $unitOfMeasure,
                    'estimated_price' => 0,
                    'currency_code' => 'MXN',
                    'status' => ProductServiceStatus::ACTIVE->value,
                    'is_active' => true,
                    'created_by' => $user->id,
                ]);
                $created++;
            }
        });

        $this->command?->info("Seeder SYC ejecutado. Creados: {$created}. Actualizados: {$updated}.");
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<string, string|null>>}
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el archivo Productos_syc.csv.');
        }

        try {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                throw new \RuntimeException('El archivo Productos_syc.csv esta vacio.');
            }

            $headers = array_map(
                static fn ($value) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $value)),
                $headers
            );

            $rows = [];
            while (($data = fgetcsv($handle)) !== false) {
                if ($data === [null] || $data === []) {
                    continue;
                }

                if (count($data) !== count($headers)) {
                    throw new \RuntimeException('Se detecto una fila con un numero de columnas distinto al esperado.');
                }

                /** @var array<string, string|null> $assoc */
                $assoc = array_combine($headers, $data);
                $rows[] = $assoc;
            }
        } finally {
            fclose($handle);
        }

        return [$headers, $rows];
    }

    private function normalizeText(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = trim($value);

        $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1, ASCII');
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }

        $replacements = [
            'Ã‘' => 'Ñ',
            'Ã±' => 'ñ',
            'Ã“' => 'Ó',
            'Ã³' => 'ó',
            'Ã‰' => 'É',
            'Ã©' => 'é',
            'Ãš' => 'Ú',
            'Ãº' => 'ú',
            'ÃЌ' => 'Í',
            'Ã­' => 'í',
            'ÃЃ' => 'Á',
            'Ã¡' => 'á',
            'Ã' => 'í',
            'Ґ' => 'Ñ',
        ];

        $value = strtr($value, $replacements);
        $value = preg_replace('/[^\P{C}\t\n\r]/u', '', $value) ?? $value;

        return Str::of($value)
            ->squish()
            ->value();
    }
}
