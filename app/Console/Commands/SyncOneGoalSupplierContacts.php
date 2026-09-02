<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SyncOneGoalSupplierContacts extends Command
{
    protected $signature = 'onegoal:sync-supplier-contacts {--dry-run : Solo muestra el resultado, sin actualizar}';
    protected $description = 'Actualiza correos reales y teléfonos de proveedores desde One Goal, vinculados por RFC.';
    private const CONNECTION = 'onegoal_contacts';

    public function handle(): int
    {
        $this->configureConnection();
        $dryRun = (bool) $this->option('dry-run');
        $updated = $emailUpdated = $phoneUpdated = $skippedEmail = 0;

        $rows = DB::connection(self::CONNECTION)->select(<<<'SQL'
            WITH ranked AS (
                SELECT rfc, email, tel1,
                    ROW_NUMBER() OVER (PARTITION BY REPLACE(REPLACE(UPPER(LTRIM(RTRIM(ISNULL(rfc, '')))), '-', ''), ' ', '') ORDER BY id_prov) AS row_number
                FROM dbo.vtt_cat_prov
                WHERE id_prov <> 0
            )
            SELECT rfc, email, tel1 FROM ranked WHERE row_number = 1
        SQL);

        foreach ($rows as $row) {
            $rfc = $this->rfc($row->rfc);
            if (! $rfc || ! ($supplier = Supplier::where('rfc', $rfc)->first())) continue;

            $changes = [];
            $email = $this->email($row->email);
            if ($email && str_ends_with(strtolower((string) $supplier->email), '@yopmail.com')) {
                $inUse = Supplier::where('email', $email)->whereKeyNot($supplier->id)->exists();
                if ($inUse) { $skippedEmail++; } else { $changes['email'] = $email; $emailUpdated++; }
            }
            $phone = $this->phone($row->tel1);
            if ($phone && $phone !== $supplier->phone_number) { $changes['phone_number'] = $phone; $changes['contact_phone'] = substr($phone, 0, 10); $phoneUpdated++; }
            if ($changes === []) continue;
            if (! $dryRun) $supplier->update($changes);
            $updated++;
        }

        $this->info(($dryRun ? 'Simulación: ' : '')."{$updated} proveedores procesados; {$emailUpdated} correos y {$phoneUpdated} teléfonos actualizados.");
        if ($skippedEmail) $this->warn("{$skippedEmail} correos se omitieron porque ya pertenecen a otro proveedor.");
        return self::SUCCESS;
    }

    private function configureConnection(): void
    {
        $password = env('ONEGOAL_DB_PASSWORD');
        if (! is_string($password) || $password === '') throw new RuntimeException('Falta configurar ONEGOAL_DB_PASSWORD.');
        Config::set('database.connections.'.self::CONNECTION, [
            'driver' => 'sqlsrv', 'host' => env('ONEGOAL_DB_HOST', '192.168.0.5'), 'port' => env('ONEGOAL_DB_PORT', '1433'),
            'database' => env('ONEGOAL_DB_DATABASE', '1G_TGS_SERVICIOSYC'), 'username' => env('ONEGOAL_DB_USERNAME', 'sa'), 'password' => $password,
            'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true, 'encrypt' => env('ONEGOAL_DB_ENCRYPT', 'no'),
            'trust_server_certificate' => env('ONEGOAL_DB_TRUST_SERVER_CERTIFICATE', 'yes'),
        ]);
        DB::purge(self::CONNECTION);
    }
    private function rfc(mixed $value): ?string { $value = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value)); return is_string($value) && strlen($value) >= 12 && strlen($value) <= 13 ? $value : null; }
    private function email(mixed $value): ?string { $value = strtolower(trim((string) $value)); return filter_var($value, FILTER_VALIDATE_EMAIL) && !str_ends_with($value, '@yopmail.com') ? $value : null; }
    private function phone(mixed $value): ?string { $value = preg_replace('/\D/', '', (string) $value); return is_string($value) && strlen($value) >= 10 ? substr($value, 0, 15) : null; }
}
