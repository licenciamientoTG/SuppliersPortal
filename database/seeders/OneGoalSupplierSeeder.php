<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OneGoalSupplierSeeder extends Seeder
{
    private const CONNECTION = 'onegoal_import';

    private int $skippedDuplicateRfcs = 0;

    public function run(): void
    {
        $this->configureOneGoalConnection();

        $rows = DB::connection(self::CONNECTION)->select($this->sourceSql());
        $password = Hash::make(env('ONEGOAL_SUPPLIER_DEFAULT_PASSWORD', 'Fl3x.2025'));
        $imported = 0;

        foreach ($rows as $row) {
            if ((int) $row->rfc_rank !== 1) {
                $this->skippedDuplicateRfcs++;

                continue;
            }

            $rfc = $this->cleanRfc($row->rfc);

            if ($rfc === null) {
                continue;
            }

            Supplier::updateOrCreate(
                ['rfc' => $rfc],
                $this->mapSupplier((array) $row, $rfc, $password)
            );

            $imported++;
        }

        $this->command?->info("OneGoal suppliers imported: {$imported}");

        if ($this->skippedDuplicateRfcs > 0) {
            $this->command?->warn("OneGoal rows skipped by duplicated RFC: {$this->skippedDuplicateRfcs}");
        }
    }

    private function configureOneGoalConnection(): void
    {
        $password = env('ONEGOAL_DB_PASSWORD');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Set ONEGOAL_DB_PASSWORD before running OneGoalSupplierSeeder.');
        }

        Config::set('database.connections.'.self::CONNECTION, [
            'driver' => 'sqlsrv',
            'host' => env('ONEGOAL_DB_HOST', '192.168.0.5'),
            'port' => env('ONEGOAL_DB_PORT', '1433'),
            'database' => env('ONEGOAL_DB_DATABASE', '1G_TGS_SERVICIOSYC'),
            'username' => env('ONEGOAL_DB_USERNAME', 'sa'),
            'password' => $password,
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('ONEGOAL_DB_ENCRYPT', 'no'),
            'trust_server_certificate' => env('ONEGOAL_DB_TRUST_SERVER_CERTIFICATE', 'yes'),
        ]);

        DB::purge(self::CONNECTION);
    }

    private function sourceSql(): string
    {
        return <<<'SQL'
            WITH normalized AS (
                SELECT
                    id_prov,
                    nom1,
                    dir1,
                    ciu,
                    est,
                    cp,
                    rfc,
                    tel1,
                    email,
                    status,
                    Contacto1,
                    id_mda,
                    tip_prov,
                    ap_pat,
                    ap_mat,
                    nombre,
                    calle,
                    num_int,
                    num_ext,
                    col,
                    deleg,
                    dias,
                    id_reg_fis,
                    REPLACE(REPLACE(REPLACE(UPPER(LTRIM(RTRIM(ISNULL(rfc, '')))), '-', ''), ' ', ''), '.', '') AS rfc_clean
                FROM dbo.vtt_cat_prov
                WHERE id_prov <> 0
            ),
            ranked AS (
                SELECT
                    *,
                    ROW_NUMBER() OVER (PARTITION BY rfc_clean ORDER BY id_prov) AS rfc_rank
                FROM normalized
                WHERE LEN(rfc_clean) BETWEEN 12 AND 13
            )
            SELECT *
            FROM ranked
            ORDER BY id_prov
        SQL;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapSupplier(array $row, string $rfc, string $password): array
    {
        $companyName = $this->limit($this->cleanText($row['nom1'] ?? null) ?? 'Proveedor OneGoal', 150);
        $personType = $this->personType($row);
        [$firstName, $lastName] = $this->personName($row, $companyName, $personType);
        $contactPerson = $this->limit(
            $this->cleanText($row['Contacto1'] ?? null) ?? trim("{$firstName} {$lastName}") ?: $companyName,
            100
        );

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $this->yopmailEmail($companyName, (int) $row['id_prov']),
            'password' => $password,
            'email_verified_at' => now(),
            'is_active' => ((int) $row['status']) === 1,
            'company_name' => $companyName,
            'address' => $this->address($row),
            'postal_code' => $this->postalCode($row['cp'] ?? null),
            'phone_number' => $this->phone($row['tel1'] ?? null, 15) ?? '0000000000',
            'contact_person' => $contactPerson,
            'contact_phone' => $this->phone($row['tel1'] ?? null, 10),
            'supplier_type' => 'product_service',
            'person_type' => $personType,
            'tax_regimes' => $this->taxRegimes((int) ($row['id_reg_fis'] ?? 0), $personType),
            'bank_name' => null,
            'account_number' => null,
            'clabe' => null,
            'currency' => $this->currency((int) ($row['id_mda'] ?? 1)),
            'default_payment_terms' => $this->paymentTerm((int) ($row['dias'] ?? 0)),
            'approval_status' => ((int) $row['status']) === 1 ? 'approved' : 'pending',
            'document_status' => 'pending',
            'provides_specialized_services' => false,
            'economic_activity' => null,
        ];
    }

    private function cleanRfc(mixed $value): ?string
    {
        $rfc = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value));

        if (! is_string($rfc) || strlen($rfc) < 12 || strlen($rfc) > 13) {
            return null;
        }

        return $rfc;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function personType(array $row): string
    {
        $rfc = $this->cleanRfc($row['rfc'] ?? null);
        $tipProv = (int) ($row['tip_prov'] ?? 0);

        if ($rfc === 'XEXX010101000') {
            return 'extranjero';
        }

        if ($tipProv === 1) {
            return 'moral';
        }

        if ($tipProv === 2) {
            return 'fisica';
        }

        return strlen((string) $rfc) === 13 ? 'fisica' : 'moral';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0:string, 1:string}
     */
    private function personName(array $row, string $companyName, string $personType): array
    {
        if ($personType === 'fisica') {
            $firstName = $this->cleanText($row['nombre'] ?? null) ?? $companyName;
            $lastName = trim(implode(' ', array_filter([
                $this->cleanText($row['ap_pat'] ?? null),
                $this->cleanText($row['ap_mat'] ?? null),
            ]))) ?: 'Proveedor';

            return [$this->limit($firstName, 100), $this->limit($lastName, 100)];
        }

        return ['Proveedor', $this->limit($companyName, 100)];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function address(array $row): string
    {
        $address = $this->cleanText($row['dir1'] ?? null);

        if ($address !== null) {
            return $address;
        }

        $parts = array_filter([
            $this->cleanText($row['calle'] ?? null),
            $this->cleanText($row['num_ext'] ?? null),
            $this->cleanText($row['num_int'] ?? null),
            $this->cleanText($row['col'] ?? null),
            $this->cleanText($row['deleg'] ?? null),
            $this->cleanText($row['ciu'] ?? null),
            $this->cleanText($row['est'] ?? null),
        ]);

        return $parts === [] ? 'SIN DIRECCION' : implode(', ', $parts);
    }

    private function postalCode(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        return substr(str_pad($digits, 5, '0', STR_PAD_LEFT), 0, 5);
    }

    private function phone(mixed $value, int $maxLength): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        return substr($digits, 0, $maxLength);
    }

    private function currency(int $idMda): string
    {
        return match ($idMda) {
            2 => 'USD',
            3 => 'EUR',
            default => 'MXN',
        };
    }

    private function paymentTerm(int $days): string
    {
        return match ($days) {
            3, 7 => 'NET_7',
            10 => 'NET_10',
            15 => 'NET_15',
            21 => 'NET_21',
            30 => 'NET_30',
            45 => 'NET_45',
            60 => 'NET_60',
            90 => 'NET_90',
            120 => 'NET_120',
            default => 'CASH',
        };
    }

    /**
     * @return array<int, array{code:string,label:string}>|null
     */
    private function taxRegimes(int $idRegFis, string $personType): ?array
    {
        $code = match ($idRegFis) {
            43800 => '601',
            43809 => '612',
            43825 => '626',
            43893 => '616',
            default => null,
        };

        if ($code === null || $personType === 'extranjero') {
            return null;
        }

        $labels = [
            '601' => 'General de Ley Personas Morales',
            '612' => 'Personas Fisicas con Actividades Empresariales y Profesionales',
            '616' => 'Sin obligaciones fiscales',
            '626' => 'Regimen Simplificado de Confianza',
        ];

        return [['code' => $code, 'label' => $labels[$code]]];
    }

    private function yopmailEmail(string $companyName, int $idProv): string
    {
        $base = Str::slug($companyName, '.');
        $base = $base === '' ? 'proveedor.onegoal' : $base;
        $local = $this->limit("{$base}.{$idProv}", 138);

        return "{$local}@yopmail.com";
    }

    private function cleanText(mixed $value): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');

        return $text === '' ? null : $text;
    }

    private function limit(string $value, int $limit): string
    {
        try {
            return Str::limit($value, $limit, '');
        } catch (Throwable) {
            return substr($value, 0, $limit);
        }
    }
}
