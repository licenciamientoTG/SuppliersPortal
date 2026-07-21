<?php

namespace App\Jobs;

use App\Services\EfosSupplierAlertService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

class SyncEfosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public string $jobId) {}

    public function handle(): void
    {
        $key = "efos_sync_{$this->jobId}";
        $this->patch($key, ['status' => 'running', 'started_at' => now()->toDateTimeString()]);
        $efosAlerts = app(EfosSupplierAlertService::class);

        try {
            $url = config('efos.csv_url');
            $dirAbs = storage_path('app/'.config('efos.storage_dir', 'efos'));
            if (! is_dir($dirAbs)) {
                mkdir($dirAbs, 0775, true);
            }
            $fullPath = $dirAbs.'/Listado_Completo_69-B.csv';

            $response = Http::withOptions(['sink' => $fullPath])->timeout(120)->retry(3, 2000)->get($url);
            if (! $response->ok()) {
                throw new \RuntimeException("No se pudo descargar el CSV (HTTP {$response->status()})");
            }

            $total = $this->countDataLines($fullPath);
            $this->patch($key, ['total' => $total, 'message' => 'Procesando registros...']);

            $processed = 0;
            $rows = [];
            $headerFound = false;
            $chunkSize = 1000;

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

            foreach ($lines as $rawLine) {
                $line = mb_convert_encoding($rawLine, 'UTF-8', 'ISO-8859-1');
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
                $rows[] = $row;
                if (count($rows) >= $chunkSize) {
                    $this->upsertChunk($rows);
                    $processed += count($rows);
                    $rows = [];
                    $this->patch($key, [
                        'processed' => $processed,
                        'message' => "Procesando... {$processed} de {$total} registros",
                    ]);
                }
            }
            if ($rows) {
                $this->upsertChunk($rows);
                $processed += count($rows);
            }

            $listedSuppliers = $efosAlerts->notifyListedActiveSuppliers();

            $this->patch($key, [
                'status' => 'completed',
                'processed' => $processed,
                'listed_suppliers' => $listedSuppliers,
                'message' => 'Sincronización completada',
                'finished_at' => now()->toDateTimeString(),
            ]);
            Log::info("SyncEfosJob completado: {$processed} registros procesados.");
        } catch (\Throwable $e) {
            try {
                $this->patch($key, [
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                    'finished_at' => now()->toDateTimeString(),
                ]);
            } catch (\Throwable) {
            }
            Log::error('SyncEfosJob falló: '.$e->getMessage());
            throw $e;
        }
    }

    private function patch(string $key, array $data): void
    {
        Cache::put($key, array_merge(Cache::get($key, []), $data), now()->addHours(2));
    }

    private function countDataLines(string $fullPath): int
    {
        $count = 0;
        $headerFound = false;
        $handle = fopen($fullPath, 'r');
        if (! $handle) {
            return 0;
        }
        while (($line = fgets($handle)) !== false) {
            $row = str_getcsv(mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1'));
            if (! $row || count($row) < 2) {
                continue;
            }
            if (! $headerFound) {
                if (stripos($row[1] ?? '', 'RFC') !== false) {
                    $headerFound = true;
                }

                continue;
            }
            $count++;
        }
        fclose($handle);

        return $count;
    }

    private function parseDate(?string $value): ?Carbon
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('d/m/Y', $v)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function upsertChunk(array $rows): void
    {
        $now = Carbon::now();
        foreach ($rows as $row) {
            if (count($row) < 16) {
                continue;
            }
            DB::statement('
                MERGE sat_efos_69b AS target
                USING (VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?))
                    AS source (number, rfc, company_name, situation,
                               sat_presumption_notice_date, sat_presumed_publication_date,
                               dof_presumption_notice_date, dof_presumed_pub_date,
                               sat_definitive_publication_date, dof_definitive_publication_date,
                               updated_at, created_at)
                ON (target.rfc = source.rfc)
                WHEN MATCHED THEN UPDATE SET
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
                WHEN NOT MATCHED THEN INSERT (
                    number, rfc, company_name, situation,
                    sat_presumption_notice_date, sat_presumed_publication_date,
                    dof_presumption_notice_date, dof_presumed_pub_date,
                    sat_definitive_publication_date, dof_definitive_publication_date,
                    updated_at, created_at)
                VALUES (
                    source.number, source.rfc, source.company_name, source.situation,
                    source.sat_presumption_notice_date, source.sat_presumed_publication_date,
                    source.dof_presumption_notice_date, source.dof_presumed_pub_date,
                    source.sat_definitive_publication_date, source.dof_definitive_publication_date,
                    source.updated_at, source.created_at);
            ', [
                (int) ($row[0] ?? 0),
                mb_substr(trim((string) ($row[1] ?? '')), 0, 13),
                mb_substr(trim((string) ($row[2] ?? '')), 0, 255),
                mb_substr(trim((string) ($row[3] ?? '')), 0, 255),
                mb_substr(trim((string) ($row[4] ?? '')), 0, 100),
                optional($this->parseDate($row[5] ?? null))->toDateString(),
                mb_substr(trim((string) ($row[6] ?? '')), 0, 100),
                optional($this->parseDate($row[7] ?? null))->toDateString(),
                optional($this->parseDate($row[13] ?? null))->toDateString(),
                optional($this->parseDate($row[15] ?? null))->toDateString(),
                $now,
                $now,
            ]);
        }
    }
}
