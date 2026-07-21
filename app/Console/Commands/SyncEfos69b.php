<?php

namespace App\Console\Commands;

use App\Services\EfosSupplierAlertService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

class SyncEfos69b extends Command
{
    protected $signature = 'efos:sync {--chunk=1000}';

    protected $description = 'Descarga el CSV 69-B del SAT y hace UPSERT en sat_efos_69b';

    public function handle(): int
    {
        Log::info('=== Inicio sincronización EFOS ===');
        $efosAlerts = app(EfosSupplierAlertService::class);

        $url = config('efos.csv_url', env('EFOS_CSV_URL'));
        $dir = config('efos.storage_dir', env('EFOS_STORAGE_DIR', 'efos'));
        $filename = 'Listado_Completo_69-B.csv';
        $path = "$dir/$filename";

        $this->info(now()->format('Y-m-d H:i:s').' - 📥 Descargando archivo desde el SAT...');
        try {
            $response = Http::timeout(120)->retry(3, 2000)->get($url);
            if (! $response->ok()) {
                $this->error("No se pudo descargar el CSV (HTTP {$response->status()})");

                return self::FAILURE;
            }
            // Guardamos tal cual (latin1) — lo convertimos línea por línea al leer
            Storage::put($path, $response->body());
        } catch (\Throwable $e) {
            $this->error('Error de descarga: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("✅ Archivo descargado: storage/app/$path");

        $dirAbs = storage_path('app/efos');
        if (! is_dir($dirAbs)) {
            mkdir($dirAbs, 0775, true);
        }
        $fullPath = $dirAbs.'/Listado_Completo_69-B.csv';

        file_put_contents($fullPath, $response->body());
        if (! is_file($fullPath)) {
            $this->error('No se encontró el archivo en disco.');

            return self::FAILURE;
        }

        $this->info('🔄 Procesando registros...');
        $chunkSize = (int) $this->option('chunk');

        // Lectura de archivo grande con LazyCollection (memoria eficiente)
        $lines = LazyCollection::make(function () use ($fullPath) {
            $handle = fopen($fullPath, 'r');
            if (! $handle) {
                yield from [];

                return;
            }
            while (($line = fgets($handle)) !== false) {
                yield $line;
            }
            fclose($handle);
        });

        // Encontrar encabezado real (fila que contenga "RFC" en la segunda columna)
        $headerFound = false;
        $rows = [];

        foreach ($lines as $rawLine) {
            // Convertimos de latin1 a utf-8 para poder explotar/str_getcsv bien
            $line = mb_convert_encoding($rawLine, 'UTF-8', 'ISO-8859-1');

            // Parse CSV de la línea (considera comas y comillas)
            $row = str_getcsv($line);

            if (! $row || count($row) < 2) {
                continue;
            }

            if (! $headerFound) {
                if (stripos($row[1] ?? '', 'RFC') !== false) {
                    $headerFound = true;
                }

                continue;
            }

            // A partir de aquí ya son datos
            $rows[] = $row;

            // Procesar por lotes para no saturar memoria
            if (count($rows) >= $chunkSize) {
                $this->upsertChunk($rows);
                $rows = [];
            }
        }

        // Último lote
        if ($rows) {
            $this->upsertChunk($rows);
        }

        $listedSuppliers = $efosAlerts->notifyListedActiveSuppliers();

        $this->info("✅ Proceso completado con éxito. Proveedores alertados: {$listedSuppliers}.");
        Log::info('✅ Proceso completado con éxito');

        return self::SUCCESS;
    }

    /**
     * Convierte dd/mm/YYYY a Carbon o null
     */
    protected function parseDate(?string $value): ?Carbon
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('d/m/Y', $v)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Recibe un lote de filas del CSV (ya en UTF-8) y hace upsert por RFC
     */
    protected function upsertChunk(array $rows): void
    {
        $now = Carbon::now();
        $payload = [];

        foreach ($rows as $row) {
            if (count($row) < 8) {
                continue; // saltar filas incompletas
            }

            $payload[] = [
                'number' => (int) ($row[0] ?? 0),
                'rfc' => mb_substr(trim((string) ($row[1] ?? '')), 0, 13),
                'company_name' => mb_substr(trim((string) ($row[2] ?? '')), 0, 255),
                'situation' => mb_substr(trim((string) ($row[3] ?? '')), 0, 255),
                'sat_presumption_notice_date' => mb_substr(trim((string) ($row[4] ?? '')), 0, 100),
                'sat_presumed_publication_date' => optional($this->parseDate($row[5] ?? null))->toDateString(),
                'dof_presumption_notice_date' => mb_substr(trim((string) ($row[6] ?? '')), 0, 100),
                'dof_presumed_pub_date' => optional($this->parseDate($row[7] ?? null))->toDateString(),
                'sat_definitive_publication_date' => optional($this->parseDate($row[13] ?? null))->toDateString(),
                'dof_definitive_publication_date' => optional($this->parseDate($row[15] ?? null))->toDateString(),
                'updated_at' => $now,
                'created_at' => $now,
            ];
        }

        if (empty($payload)) {
            return;
        }

        foreach ($payload as $record) {
            $params = [
                $record['number'],
                $record['rfc'],
                $record['company_name'],
                $record['situation'],
                $record['sat_presumption_notice_date'],
                $record['sat_presumed_publication_date'],
                $record['dof_presumption_notice_date'],
                $record['dof_presumed_pub_date'],
                $record['sat_definitive_publication_date'],
                $record['dof_definitive_publication_date'],
                $record['updated_at'],
                $record['created_at'],
            ];

            $sql = '
                MERGE sat_efos_69b AS target
                USING (VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?))
                    AS source (number, rfc, company_name, situation, sat_presumption_notice_date,
                            sat_presumed_publication_date, dof_presumption_notice_date,
                            dof_presumed_pub_date, sat_definitive_publication_date,
                            dof_definitive_publication_date, updated_at, created_at)
                ON (target.rfc = source.rfc)
                WHEN MATCHED THEN
                    UPDATE SET
                        number = source.number,
                        company_name = source.company_name,
                        situation = source.situation,
                        sat_presumption_notice_date = source.sat_presumption_notice_date,
                        sat_presumed_publication_date = source.sat_presumed_publication_date,
                        dof_presumption_notice_date = source.dof_presumption_notice_date,
                        dof_presumed_pub_date = source.dof_presumed_pub_date,
                        sat_definitive_publication_date = source.sat_definitive_publication_date,
                        dof_definitive_publication_date = source.dof_definitive_publication_date,
                        updated_at = source.updated_at
                WHEN NOT MATCHED THEN
                    INSERT (number, rfc, company_name, situation, sat_presumption_notice_date,
                        sat_presumed_publication_date, dof_presumption_notice_date,
                        dof_presumed_pub_date, sat_definitive_publication_date,
                        dof_definitive_publication_date, updated_at, created_at)
                    VALUES (source.number, source.rfc, source.company_name, source.situation,
                        source.sat_presumption_notice_date, source.sat_presumed_publication_date,
                        source.dof_presumption_notice_date, source.dof_presumed_pub_date,
                        source.sat_definitive_publication_date, source.dof_definitive_publication_date,
                        source.updated_at, source.created_at);
            ';

            DB::statement($sql, $params);
        }

        $this->line('➡️ Lote upsert: '.count($payload).' registros');
    }
}
