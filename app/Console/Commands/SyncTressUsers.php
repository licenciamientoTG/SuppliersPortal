<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\BudgetProfileHomologationService;

class SyncTressUsers extends Command
{
    /**
     * Nombre del comando:
     *   php artisan users:sync-tress --dry-run
     */
    protected $signature = 'users:sync-tress {--dry-run : Simula la importación sin escribir cambios}';

    protected $description = 'Importa y actualiza empleados desde CSV de TRESS y crea/actualiza usuarios del sistema SÓLO si el CSV trae correo.';

    public function handle(): int
    {
        $disk = Storage::disk('tress');

        $files = collect($disk->files())
            ->filter(fn ($p) => preg_match('/^Empleados.*\.csv$/i', basename($p)))
            ->values();

        if ($files->isEmpty()) {
            $this->warn('No se encontraron archivos que inicien con "Empleados" en el disk tress.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $createdEmployees = 0;
        $updatedEmployees = 0;
        $createdUsers     = 0;
        $updatedUsers     = 0;
        $skippedUsersNoEmail = 0;
        $skippedCollisions   = 0;

        foreach ($files as $path) {
            $company = $this->inferCompanyFromFilename(basename($path));
            $this->info("Procesando: " . basename($path) . "  (Empresa: {$company})");

            $raw  = $disk->get($path);
            $text = $this->normalizeEncoding($raw);

            $rows = $this->parseCsvWithHeader($text);
            if (empty($rows)) {
                $this->warn("Archivo sin filas (o sin encabezado) → " . basename($path));
                continue;
            }

            foreach ($rows as $row) {
                // ===== Mapear columnas esperadas de TRESS =====
                $employeeNumber = $row['Número']               ?? null;
                $fullName       = $row['Nombre']               ?? null;
                $department     = $row['Departamento']         ?? null;
                $jobTitle       = $row['Puesto']               ?? null;
                $hireDate       = $this->asDate($row['Fecha de Ingreso'] ?? null);
                $isActiveFlag   = $this->asBool($row['Activo'] ?? null);
                $termination    = $this->asDate($row['Fecha de Baja'] ?? null);
                $rehireEligible = $this->asBool($row['Recontratar'] ?? null);
                $termReason     = $row['Motivo de Baja']       ?? null;
                $team           = $row['Equipo']               ?? null;
                $seniority      = $row['Antigüedad']           ?? null;
                $rfc            = $this->limpia($row['RFC']    ?? null);
                $imss           = $this->limpia($row['IMSS']   ?? null);
                $curp           = $this->limpia($row['CURP']   ?? null);
                $gender         = $row['Género']               ?? null;
                $vacBalance     = $this->asDecimal($row['SaldodeVacaciones'] ?? null);
                $phone          = $row['Teléfono']             ?? null;
                $address        = $row['Dirección']            ?? null;

                // Correo: SOLO si viene en el CSV (no inventamos nada)
                $email          = $this->extractEmail($row); // null si no existe alguna cabecera válida

                // ===== Buscar Employee existente (prioridad: CURP > RFC > employee_number+company) =====
                $employee = Employee::query()
                    ->when($curp, fn ($q) => $q->where('curp', $curp))
                    ->when(!$curp && $rfc, fn ($q) => $q->where('rfc', $rfc))
                    ->when(!$curp && !$rfc && $employeeNumber, fn ($q) =>
                        $q->where('employee_number', $employeeNumber)
                          ->where('company', $company)
                    )
                    ->first();

                // ===== Payload para crear/actualizar employees =====
                $employeePayload = [
                    'company'            => $company,
                    'employee_number'    => $employeeNumber,
                    'full_name'          => $fullName,
                    'department'         => $department,
                    'job_title'          => $jobTitle,
                    'hire_date'          => $hireDate,
                    'is_active'          => $isActiveFlag ?? true,
                    'termination_date'   => $termination,
                    'rehire_eligible'    => $rehireEligible,
                    'termination_reason' => $termReason,
                    'team'               => $team,
                    'seniority'          => $seniority,
                    'rfc'                => $rfc,
                    'imss'               => $imss,
                    'curp'               => $curp,
                    'gender'             => $gender,
                    'vacation_balance'   => $vacBalance,
                    'phone'              => $phone,
                    'address'            => $address,
                ];

                if (!$employee) {
                    if (!$dry) {
                        $employee = Employee::create($employeePayload);
                    }
                    $createdEmployees++;
                } else {
                    if (!$dry) {
                        $employee->fill($employeePayload)->save();
                    }
                    $updatedEmployees++;
                }

                // ===== Usuario del sistema (User) =====
                // Reglas:
                //  - SOLO si hay $email en el CSV creamos/actualizamos el User.
                //  - No generamos correos ni usernames alternos.
                //  - La única info “nueva” que agregamos es la contraseña.
                //  - No reasignamos un User a otro empleado.
                $userIsActive = ($isActiveFlag ?? true) && !$termination;

                if (!$email) {
                    $skippedUsersNoEmail++;
                    $this->line("  · Sin email en CSV → NO se crea/actualiza User (EmpNo: ".($employeeNumber ?? 'N/A').")");
                    continue;
                }

                // Tengo email del CSV:
                $employeeId = $employee?->id;

                // Si el employee ya tiene user, lo usamos.
                $user = $employee?->user;

                if (!$user) {
                    // Buscar por email exacto
                    $user = User::where('email', $email)->first();
                }

                $defaultPass = $this->defaultPassword($rfc, $hireDate);

                if ($user) {
                    // Verificar colisión: ¿este user ya está ligado a otro employee distinto?
                    $otherEmployee = Employee::where('user_id', $user->id)->first();
                    $isCollision   = $otherEmployee && ($employeeId === null || $otherEmployee->id !== $employeeId);

                    if ($isCollision) {
                        // No movemos nada, no inventamos nuevos users ni emails
                        $skippedCollisions++;
                        $this->warn("  · Colisión: el correo {$email} pertenece a otro empleado (User #{$user->id}). NO se reasigna.");
                    } else {
                        if (!$dry) {
                            // Actualizamos sólo con datos que vienen del CSV (más password)
                            $user->name                 = $fullName ?: ($user->name ?? ''); // nombre desde CSV si viene
                            $user->is_active            = $userIsActive;
                            // No tocamos $user->email (salvo que ya sea el mismo)
                            if ($user->email !== $email) {
                                // Si el user NO pertenece a nadie, permitiría actualizar, pero para ser estrictos:
                                // Sólo actualizamos si coincide (evita cambiar a algo distinto a lo que ya haya).
                                $this->warn("  · Aviso: el User #{$user->id} tiene email distinto ({$user->email}) vs CSV ({$email}). No se cambia por política.");
                            }
                            $user->password             = Hash::make($defaultPass);
                            $user->must_change_password = true;
                            $user->save();

                            if ($employeeId && !$employee->user_id) {
                                $employee->update(['user_id' => $user->id]);
                            }
                            app(BudgetProfileHomologationService::class)->assignBudgetProfileFromEmployee($user, $employee);
                        }
                        $updatedUsers++;
                    }
                } else {
                    // No hay user: crearlo SÓLO con data del CSV (más password)
                    if (!$dry) {
                        $user = User::create([
                            'name'                 => $fullName ?: $email,
                            'email'                => $email,
                            'password'             => Hash::make($defaultPass), // ÚNICO dato “extra”
                            'is_active'            => $userIsActive,
                            'must_change_password' => true,
                        ]);
                        if ($employeeId && !$employee->user_id) {
                            $employee->update(['user_id' => $user->id]);
                        }
                        app(BudgetProfileHomologationService::class)->assignBudgetProfileFromEmployee($user, $employee);
                    }
                    $createdUsers++;
                }
            }
        }

        $this->info("Employees: +{$createdEmployees} creados, {$updatedEmployees} actualizados");
        $this->info("Users:     +{$createdUsers} creados, {$updatedUsers} actualizados");
        $this->info("Saltados:  {$skippedUsersNoEmail} sin email en CSV, {$skippedCollisions} colisiones de email");

        if ($dry) {
            $this->comment('** Modo simulación (no se escribieron cambios). **');
        }

        return self::SUCCESS;
    }

    /**
     * "Empleados Aqua Car Club.csv" => "Aqua Car Club"
     */
    private function inferCompanyFromFilename(string $filename): string
    {
        $base = preg_replace('/^Empleados\s*/i', '', pathinfo($filename, PATHINFO_FILENAME));
        return trim((string) $base);
    }

    private function normalizeEncoding(string $raw): string
    {
        if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
            $raw = substr($raw, 3);
        }
        $enc = mb_detect_encoding($raw, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UTF-8';
        return $enc === 'UTF-8' ? $raw : mb_convert_encoding($raw, 'UTF-8', $enc);
    }

    private function parseCsvWithHeader(string $text): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $text);
        $lines = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));

        if (count($lines) === 0) return [];

        $delimiter = ',';
        $first     = $lines[0];
        $commas    = substr_count($first, ',');
        $semis     = substr_count($first, ';');
        if ($semis > $commas) $delimiter = ';';

        $header = str_getcsv($first, $delimiter);
        $header = array_map(fn ($h) => trim($h), $header);

        $rows = [];
        for ($i = 1; $i < count($lines); $i++) {
            $cols  = str_getcsv($lines[$i], $delimiter);
            $assoc = [];
            foreach ($header as $idx => $h) {
                $assoc[$h] = $cols[$idx] ?? null;
            }
            $rows[] = $assoc;
        }

        return $rows;
    }

    private function asDate(?string $val): ?string
    {
        if (!$val) return null;
        $val = trim($val);

        if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $val)) {
            [$d, $m, $y] = explode('/', $val);
            return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
        }

        if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $val)) {
            return $val;
        }

        return null;
    }

    private function asBool($val): ?bool
    {
        if ($val === null) return null;
        $v = mb_strtolower(trim((string) $val));

        $truthy = ['1', 'si', 'sí', 'true', 'activo', 'activa', 'y', 'yes', 's'];
        $falsy  = ['0', 'no', 'false', 'inactivo', 'inactiva', 'n'];

        if (in_array($v, $truthy, true)) return true;
        if (in_array($v, $falsy, true))  return false;

        return null;
    }

    private function asDecimal($val): ?string
    {
        if ($val === null || $val === '') return null;

        $clean = str_replace(['.', ','], ['', '.'], (string) $val);
        $clean = preg_replace('/[^0-9.\-]/', '', $clean);

        if (!is_numeric($clean)) return null;

        $num = (float) $clean;

        if ($num > 999999.99) $num = 999999.99;
        if ($num < 0) $num = 0;

        return number_format($num, 2, '.', '');
    }

    private function limpia(?string $v): ?string
    {
        if ($v === null) return null;
        $v = trim($v);
        return $v === '' ? null : $v;
    }

    /**
     * Genera contraseña por defecto: Tg-<RFC_ult4>-<AAAA>
     * (ÚNICO dato “extra” permitido por requerimiento)
     */
    private function defaultPassword(?string $rfc, ?string $hireDate): string
    {
        $year  = $hireDate ? (int) substr($hireDate, 0, 4) : (int) date('Y');
        $last4 = $rfc ? substr(preg_replace('/\s+/', '', $rfc), -4) : '0000';
        return "Tg-{$last4}-{$year}";
    }

    /**
     * Extrae email SÓLO si existe en el CSV (sin inventar nada).
     * Acepta varias variantes de cabecera.
     */
    private function extractEmail(array $row): ?string
    {
        $candidatos = ['Correo', 'Email', 'E-mail', 'e-mail', 'Correo Electrónico', 'correo', 'email'];
        foreach ($candidatos as $key) {
            if (array_key_exists($key, $row)) {
                $email = trim((string) $row[$key]);
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $email;
                }
            }
        }
        return null;
    }
}
