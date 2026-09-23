# Respaldos manuales de base de datos — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que dos usuarios autorizados generen desde el menú de perfil un `.bak` nativo de SQL Server, guardado dentro del proyecto, rotado a 3 copias, con listado de detalles y descarga.

**Architecture:** `DatabaseBackupService` ejecuta `BACKUP DATABASE ... TO DISK` hacia una ruta UNC que apunta a `storage/app/private/backups` del servidor de la app, registra cada intento en la tabla `database_backups` y aplica la rotación. Un Gate `manage-db-backups` basado en una lista de correos (excluido del bypass de superadmin) protege las rutas `/admin/db-backups`. La UI es una vista Blade con el layout `zircos` y una entrada en el dropdown de perfil del navbar.

**Tech Stack:** Laravel 12, SQL Server (`sqlsrv`), PHPUnit sobre SQLite en memoria, spatie/laravel-activitylog, Blade + Bootstrap (tema Zircos, iconos Tabler `ti`).

**Spec:** `docs/superpowers/specs/2026-09-23-db-backups-design.md`

## Global Constraints

- PHP: usar `C:\PHP83\php.exe` (el `php` del PATH es 8.5 sin GD). Comando de pruebas: `C:/PHP83/php.exe artisan test --filter=<Nombre>`.
- Correos autorizados por defecto: `aldo.ochoa@totalgas.com`, `daniel.ramirez@totalgas.com`; comparación sin distinguir mayúsculas.
- Copias a conservar: `3` (`DB_BACKUP_KEEP`). Timeout: `1800` s (`DB_BACKUP_TIMEOUT`).
- Ruta local por defecto: `storage_path('app/private/backups')` (ya ignorada por git vía `storage/app/private/.gitignore`; no se necesita tocar `.gitignore`).
- SQL: `BACKUP DATABASE [<db>] TO DISK = N'<sql_path>\<file>' WITH COPY_ONLY, INIT, CHECKSUM, NAME = N'Portal manual backup'`.
- Un superadmin fuera de la lista recibe 403. La entrada de menú vive en el dropdown de perfil, **no** en el sidebar.
- Textos de UI en español. Pruebas nunca apuntan a la BD operativa (no tocar `phpunit.xml`).
- Formatear con `C:/PHP83/php.exe vendor/bin/pint <archivos>` antes de cada commit.
- Commits terminan con `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Doble clic / dos usuarios a la vez** → el segundo intento se rechaza con "Ya hay un respaldo en curso" y no crea registro (test en Task 2 `test_refuses_when_another_backup_is_running`).
2. **SQL Server escribe en la ruta UNC pero la app no ve el archivo (share mal apuntado)** → registro `failed` con mensaje que menciona ambas variables; no borra copias previas (Task 2 `test_marks_failed_when_file_is_not_visible_locally`).
3. **Correo con mayúsculas o espacios en `.env`/usuario** → sigue autorizado (Task 3 `test_allowed_email_is_case_insensitive`).
4. **Descargar un respaldo `failed`/`running` o cuyo archivo ya no existe** → 404, nunca error 500 (Task 3 `test_download_returns_404_for_failed_or_missing_file`).
5. **`DB_BACKUP_SQL_PATH` sin configurar** → botón deshabilitado, aviso visible y el POST devuelve error amigable sin crear registro (Task 3 `test_unconfigured_path_shows_notice_and_rejects_store`).

---

## File Structure

| Archivo | Responsabilidad |
|---|---|
| `config/db_backups.php` (nuevo) | Configuración del módulo |
| `database/migrations/2026_09_23_000001_create_database_backups_table.php` (nuevo) | Tabla de registros |
| `app/Models/DatabaseBackup.php` (nuevo) | Modelo, estados, `human_size`, `duration_seconds` |
| `app/Exceptions/DatabaseBackupException.php` (nuevo) | Errores con mensaje apto para el usuario |
| `app/Services/DatabaseBackupService.php` (nuevo) | Lock, BACKUP, verificación, rotación, activitylog |
| `app/Http/Controllers/DatabaseBackupController.php` (nuevo) | index / store / download |
| `resources/views/db-backups/index.blade.php` (nuevo) | Listado y botón |
| `app/Providers/AppServiceProvider.php` (modificar) | Gate + exclusión del bypass superadmin |
| `routes/web.php` (modificar) | Rutas `db-backups.*` |
| `resources/views/layouts/partials/navbar.blade.php` (modificar ~L377-383) | Entrada en dropdown de perfil |
| `.env.example` (modificar) | Documentar variables |
| `tests/Support/FakeDatabaseBackupService.php` (nuevo) | Doble de prueba que simula el BACKUP |
| `tests/Feature/DatabaseBackupServiceTest.php` (nuevo) | Pruebas del servicio y modelo |
| `tests/Feature/DatabaseBackupAccessTest.php` (nuevo) | Pruebas HTTP, autorización, UI |

---

### Task 1: Configuración, tabla y modelo

**Files:**
- Create: `config/db_backups.php`
- Create: `database/migrations/2026_09_23_000001_create_database_backups_table.php`
- Create: `app/Models/DatabaseBackup.php`
- Modify: `.env.example` (al final)
- Test: `tests/Feature/DatabaseBackupServiceTest.php`

**Interfaces:**
- Produces: `config('db_backups.allowed_emails'): string[]` (minúsculas, sin espacios), `config('db_backups.sql_path'): ?string`, `config('db_backups.local_path'): string`, `config('db_backups.keep'): int`, `config('db_backups.timeout'): int`.
- Produces: `App\Models\DatabaseBackup` con constantes `STATUS_RUNNING='running'`, `STATUS_COMPLETED='completed'`, `STATUS_FAILED='failed'`; atributos fillable `filename, database_name, size_bytes, status, error_message, created_by, started_at, finished_at`; `creator(): BelongsTo<User>`; scope `completed()`; accessors `human_size: string`, `duration_seconds: ?int`.

- [ ] **Step 1: Write the failing test**

Crear `tests/Feature/DatabaseBackupServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_defaults_list_both_authorized_emails(): void
    {
        // Se lee el archivo directamente porque las pruebas del servicio sobrescriben estos valores en setUp.
        $defaults = require config_path('db_backups.php');

        $this->assertSame(['aldo.ochoa@totalgas.com', 'daniel.ramirez@totalgas.com'], $defaults['allowed_emails']);
        $this->assertSame(3, $defaults['keep']);
        $this->assertSame(storage_path('app/private/backups'), $defaults['local_path']);
    }

    public function test_human_size_formats_bytes(): void
    {
        $this->assertSame('—', (new DatabaseBackup(['size_bytes' => null]))->human_size);
        $this->assertSame('0 B', (new DatabaseBackup(['size_bytes' => 0]))->human_size);
        $this->assertSame('1.50 KB', (new DatabaseBackup(['size_bytes' => 1536]))->human_size);
        $this->assertSame('5.00 GB', (new DatabaseBackup(['size_bytes' => 5368709120]))->human_size);
    }

    public function test_duration_seconds_uses_start_and_finish(): void
    {
        $backup = new DatabaseBackup([
            'started_at' => '2026-09-23 10:00:00',
            'finished_at' => '2026-09-23 10:01:05',
        ]);

        $this->assertSame(65, $backup->duration_seconds);
        $this->assertNull((new DatabaseBackup)->duration_seconds);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `C:/PHP83/php.exe artisan test --filter=DatabaseBackupServiceTest`
Expected: FAIL — `Class "App\Models\DatabaseBackup" not found` / config nula.

- [ ] **Step 3: Write minimal implementation**

`config/db_backups.php`:

```php
<?php

return [

    /*
    | Correos autorizados para generar, ver y descargar respaldos (CSV en .env).
    */
    'allowed_emails' => array_values(array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('DB_BACKUP_ALLOWED_EMAILS', 'aldo.ochoa@totalgas.com,daniel.ramirez@totalgas.com'))
    ))),

    /*
    | Carpeta destino tal como la ve SQL Server (ruta UNC al share del servidor de la app).
    */
    'sql_path' => env('DB_BACKUP_SQL_PATH'),

    /*
    | La misma carpeta vista desde la aplicación.
    */
    'local_path' => env('DB_BACKUP_LOCAL_PATH', storage_path('app/private/backups')),

    'keep' => (int) env('DB_BACKUP_KEEP', 3),

    'timeout' => (int) env('DB_BACKUP_TIMEOUT', 1800),

];
```

`database/migrations/2026_09_23_000001_create_database_backups_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->unique();
            $table->string('database_name');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status', 20)->default('running');
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_backups');
    }
};
```

`app/Models/DatabaseBackup.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseBackup extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'filename',
        'database_name',
        'size_bytes',
        'status',
        'error_message',
        'created_by',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function getHumanSizeAttribute(): string
    {
        if ($this->size_bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $this->size_bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $unit === 0 ? 0 : 2).' '.$units[$unit];
    }

    public function getDurationSecondsAttribute(): ?int
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->finished_at);
    }
}
```

Añadir al final de `.env.example`:

```dotenv

# Respaldos manuales de BD (SQL Server BACKUP DATABASE)
# DB_BACKUP_SQL_PATH: carpeta destino vista desde SQL Server (UNC al share del servidor de la app)
# DB_BACKUP_LOCAL_PATH: la misma carpeta vista desde la app; omitir para usar storage/app/private/backups
#   (una línea vacía "DB_BACKUP_LOCAL_PATH=" anula el default, por eso va comentada)
DB_BACKUP_ALLOWED_EMAILS=aldo.ochoa@totalgas.com,daniel.ramirez@totalgas.com
DB_BACKUP_SQL_PATH=
# DB_BACKUP_LOCAL_PATH=
DB_BACKUP_KEEP=3
DB_BACKUP_TIMEOUT=1800
```

- [ ] **Step 4: Run test to verify it passes**

Run: `C:/PHP83/php.exe artisan test --filter=DatabaseBackupServiceTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
C:/PHP83/php.exe vendor/bin/pint config/db_backups.php app/Models/DatabaseBackup.php database/migrations/2026_09_23_000001_create_database_backups_table.php tests/Feature/DatabaseBackupServiceTest.php
git add config/db_backups.php app/Models/DatabaseBackup.php database/migrations/2026_09_23_000001_create_database_backups_table.php tests/Feature/DatabaseBackupServiceTest.php .env.example
git commit -m "feat: add database backups config, table and model"
```

---

### Task 2: Servicio de respaldo con rotación

**Files:**
- Create: `app/Exceptions/DatabaseBackupException.php`
- Create: `app/Services/DatabaseBackupService.php`
- Create: `tests/Support/FakeDatabaseBackupService.php`
- Modify: `tests/Feature/DatabaseBackupServiceTest.php`

**Interfaces:**
- Consumes: `DatabaseBackup` y `config('db_backups.*')` de Task 1.
- Produces:
  - `App\Exceptions\DatabaseBackupException extends \RuntimeException` (mensaje apto para mostrar).
  - `App\Services\DatabaseBackupService`:
    - `isConfigured(): bool`
    - `localPath(): string` (sin separador final)
    - `localFileFor(DatabaseBackup $backup): string`
    - `buildBackupSql(string $database, string $filename): string`
    - `create(User $user): DatabaseBackup` — lanza `DatabaseBackupException` (no configurado, lock tomado, archivo no visible) o relanza el error del BACKUP.
    - `protected runBackupStatement(string $sql, string $expectedLocalFile): void` — punto de extensión para pruebas.
  - Lock de caché con clave `'db-backup'`.
  - `Tests\Support\FakeDatabaseBackupService` con propiedades públicas `array $statements`, `?\Throwable $failWith`, `bool $writeFile = true`, `string $contents`.

- [ ] **Step 1: Write the failing tests**

Crear `tests/Support/FakeDatabaseBackupService.php`:

```php
<?php

namespace Tests\Support;

use App\Services\DatabaseBackupService;

class FakeDatabaseBackupService extends DatabaseBackupService
{
    /** @var string[] */
    public array $statements = [];

    public ?\Throwable $failWith = null;

    public bool $writeFile = true;

    public string $contents = 'fake-bak-contents';

    protected function runBackupStatement(string $sql, string $expectedLocalFile): void
    {
        $this->statements[] = $sql;

        // Escribe antes de fallar para simular un archivo parcial.
        if ($this->writeFile) {
            file_put_contents($expectedLocalFile, $this->contents);
        }

        if ($this->failWith) {
            throw $this->failWith;
        }
    }
}
```

Reemplazar la cabecera de `tests/Feature/DatabaseBackupServiceTest.php` (imports, propiedades, `setUp`/`tearDown`) y añadir los tests nuevos debajo de los de Task 1:

```php
<?php

namespace Tests\Feature;

use App\Exceptions\DatabaseBackupException;
use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\Support\FakeDatabaseBackupService;
use Tests\TestCase;

class DatabaseBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    private FakeDatabaseBackupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'db-backups-'.uniqid();

        config([
            'db_backups.local_path' => $this->backupDir,
            'db_backups.sql_path' => '\\\\APP-SERVER\\portal-backups',
            'db_backups.keep' => 3,
        ]);

        $this->service = new FakeDatabaseBackupService;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupDir);

        parent::tearDown();
    }

    // Aquí siguen, sin cambios, los 3 tests de Task 1:
    // test_config_defaults_list_both_authorized_emails, test_human_size_formats_bytes,
    // test_duration_seconds_uses_start_and_finish.

    public function test_creates_completed_backup_with_size_and_native_sql(): void
    {
        $user = User::factory()->create();
        $this->service->contents = 'abc123';

        $backup = $this->service->create($user);

        $this->assertSame(DatabaseBackup::STATUS_COMPLETED, $backup->status);
        $this->assertSame(6, $backup->size_bytes);
        $this->assertSame($user->id, $backup->created_by);
        $this->assertNotNull($backup->finished_at);
        $this->assertFileExists($this->backupDir.DIRECTORY_SEPARATOR.$backup->filename);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+_\d{8}_\d{6}\.bak$/', $backup->filename);
        $this->assertStringContainsString(
            "TO DISK = N'\\\\APP-SERVER\\portal-backups\\{$backup->filename}'",
            $this->service->statements[0]
        );
        $this->assertStringContainsString('WITH COPY_ONLY, INIT, CHECKSUM', $this->service->statements[0]);
    }

    public function test_build_sql_escapes_database_name_and_path(): void
    {
        config(['db_backups.sql_path' => "\\\\srv\\o'brien\\"]);

        $this->assertSame(
            "BACKUP DATABASE [we]]ird] TO DISK = N'\\\\srv\\o''brien\\x.bak' WITH COPY_ONLY, INIT, CHECKSUM, NAME = N'Portal manual backup'",
            $this->service->buildBackupSql('we]ird', 'x.bak')
        );
    }

    public function test_keeps_only_the_three_newest_backups(): void
    {
        $user = User::factory()->create();
        $created = [];

        foreach (range(1, 4) as $i) {
            $created[] = $this->service->create($user);
            $this->travel(1)->minutes();
        }

        $this->assertSame(3, DatabaseBackup::count());
        $this->assertDatabaseMissing('database_backups', ['id' => $created[0]->id]);
        $this->assertFileDoesNotExist($this->backupDir.DIRECTORY_SEPARATOR.$created[0]->filename);

        foreach (array_slice($created, 1) as $backup) {
            $this->assertFileExists($this->backupDir.DIRECTORY_SEPARATOR.$backup->filename);
        }
    }

    public function test_failure_marks_failed_and_keeps_previous_backups(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $i) {
            $this->service->create($user);
            $this->travel(1)->minutes();
        }

        $this->service->failWith = new \RuntimeException('boom');

        try {
            $this->service->create($user);
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $failed = DatabaseBackup::where('status', DatabaseBackup::STATUS_FAILED)->sole();
        $this->assertSame('boom', $failed->error_message);
        $this->assertFileDoesNotExist($this->backupDir.DIRECTORY_SEPARATOR.$failed->filename);
        $this->assertSame(3, DatabaseBackup::completed()->count());
        $this->assertCount(3, File::files($this->backupDir));
    }

    public function test_marks_failed_when_file_is_not_visible_locally(): void
    {
        $this->service->writeFile = false;

        try {
            $this->service->create(User::factory()->create());
            $this->fail('Expected exception');
        } catch (DatabaseBackupException $e) {
            $this->assertStringContainsString('DB_BACKUP_SQL_PATH', $e->getMessage());
            $this->assertStringContainsString('DB_BACKUP_LOCAL_PATH', $e->getMessage());
        }

        $this->assertSame(DatabaseBackup::STATUS_FAILED, DatabaseBackup::sole()->status);
    }

    public function test_failed_records_are_pruned_after_next_success(): void
    {
        $user = User::factory()->create();
        $this->service->failWith = new \RuntimeException('boom');

        try {
            $this->service->create($user);
        } catch (\RuntimeException) {
        }

        $this->travel(1)->minutes();
        $this->service->failWith = null;
        $this->service->create($user);

        $this->assertSame(0, DatabaseBackup::where('status', DatabaseBackup::STATUS_FAILED)->count());
        $this->assertSame(1, DatabaseBackup::count());
    }

    public function test_refuses_when_another_backup_is_running(): void
    {
        $lock = Cache::lock('db-backup', 10);
        $lock->get();

        try {
            $this->service->create(User::factory()->create());
            $this->fail('Expected exception');
        } catch (DatabaseBackupException $e) {
            $this->assertStringContainsString('en curso', $e->getMessage());
        } finally {
            $lock->release();
        }

        $this->assertSame(0, DatabaseBackup::count());
        $this->assertSame([], $this->service->statements);
    }

    public function test_refuses_when_sql_path_is_not_configured(): void
    {
        config(['db_backups.sql_path' => null]);

        $this->assertFalse($this->service->isConfigured());
        $this->expectException(DatabaseBackupException::class);

        try {
            $this->service->create(User::factory()->create());
        } finally {
            $this->assertSame(0, DatabaseBackup::count());
        }
    }

    public function test_logs_activity_for_created_backup(): void
    {
        $backup = $this->service->create(User::factory()->create());

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => DatabaseBackup::class,
            'subject_id' => $backup->id,
            'description' => 'Respaldo de base de datos generado',
        ]);
    }
}
```

Los 3 tests de Task 1 se conservan tal cual dentro de la clase.

- [ ] **Step 2: Run tests to verify they fail**

Run: `C:/PHP83/php.exe artisan test --filter=DatabaseBackupServiceTest`
Expected: FAIL — `Class "App\Services\DatabaseBackupService" not found`.

- [ ] **Step 3: Write minimal implementation**

`app/Exceptions/DatabaseBackupException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de respaldo cuyo mensaje puede mostrarse directamente al usuario.
 */
class DatabaseBackupException extends RuntimeException {}
```

`app/Services/DatabaseBackupService.php`:

```php
<?php

namespace App\Services;

use App\Exceptions\DatabaseBackupException;
use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class DatabaseBackupService
{
    private const LOCK_KEY = 'db-backup';

    public function isConfigured(): bool
    {
        return filled(config('db_backups.sql_path'));
    }

    public function localPath(): string
    {
        return rtrim((string) config('db_backups.local_path'), '\\/');
    }

    public function localFileFor(DatabaseBackup $backup): string
    {
        return $this->localPath().DIRECTORY_SEPARATOR.basename($backup->filename);
    }

    public function buildBackupSql(string $database, string $filename): string
    {
        $target = rtrim((string) config('db_backups.sql_path'), '\\/').'\\'.$filename;

        return sprintf(
            "BACKUP DATABASE [%s] TO DISK = N'%s' WITH COPY_ONLY, INIT, CHECKSUM, NAME = N'Portal manual backup'",
            str_replace(']', ']]', $database),
            str_replace("'", "''", $target)
        );
    }

    public function create(User $user): DatabaseBackup
    {
        if (! $this->isConfigured()) {
            throw new DatabaseBackupException('La ruta de respaldos (DB_BACKUP_SQL_PATH) no está configurada.');
        }

        $timeout = (int) config('db_backups.timeout', 1800);
        $lock = Cache::lock(self::LOCK_KEY, $timeout);

        if (! $lock->get()) {
            throw new DatabaseBackupException('Ya hay un respaldo en curso. Intenta de nuevo en unos minutos.');
        }

        try {
            return $this->runBackup($user, $timeout);
        } finally {
            $lock->release();
        }
    }

    /**
     * Ejecuta el BACKUP en SQL Server. Con PDO_SQLSRV el comando emite varios
     * mensajes informativos; hay que consumir todos los rowsets o la llamada
     * regresa antes de que el respaldo termine.
     */
    protected function runBackupStatement(string $sql, string $expectedLocalFile): void
    {
        $statement = DB::connection()->getPdo()->prepare($sql);
        $statement->execute();

        do {
            // Consumir mensajes "Processed N pages..." hasta el final.
        } while ($statement->nextRowset());
    }

    private function runBackup(User $user, int $timeout): DatabaseBackup
    {
        File::ensureDirectoryExists($this->localPath());

        $database = DB::connection()->getDatabaseName();
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $database).'_'.now()->format('Ymd_His').'.bak';

        $backup = DatabaseBackup::create([
            'filename' => $filename,
            'database_name' => $database,
            'status' => DatabaseBackup::STATUS_RUNNING,
            'created_by' => $user->id,
            'started_at' => now(),
        ]);

        $localFile = $this->localFileFor($backup);

        try {
            @set_time_limit($timeout);

            $this->runBackupStatement($this->buildBackupSql($database, $filename), $localFile);

            clearstatcache(true, $localFile);

            if (! is_file($localFile)) {
                throw new DatabaseBackupException(
                    'SQL Server terminó el respaldo pero el archivo no aparece en '.$this->localPath().
                    '. Verifica que DB_BACKUP_SQL_PATH y DB_BACKUP_LOCAL_PATH apunten a la misma carpeta.'
                );
            }

            $backup->update([
                'status' => DatabaseBackup::STATUS_COMPLETED,
                'size_bytes' => filesize($localFile),
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            if (is_file($localFile)) {
                @unlink($localFile);
            }

            $backup->update([
                'status' => DatabaseBackup::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            activity()
                ->performedOn($backup)
                ->causedBy($user)
                ->withProperty('error', $e->getMessage())
                ->log('Respaldo de base de datos fallido');

            throw $e;
        }

        activity()
            ->performedOn($backup)
            ->causedBy($user)
            ->withProperty('size_bytes', $backup->size_bytes)
            ->log('Respaldo de base de datos generado');

        $this->prune($backup, $user);

        return $backup->fresh();
    }

    private function prune(DatabaseBackup $latest, User $user): void
    {
        $keep = max(1, (int) config('db_backups.keep', 3));

        $stale = DatabaseBackup::completed()->orderByDesc('id')->get()->slice($keep);

        foreach ($stale as $old) {
            $file = $this->localFileFor($old);

            if (is_file($file) && ! @unlink($file)) {
                // Se conserva el registro para que el excedente sea visible en la UI.
                Log::warning('No se pudo eliminar el respaldo rotado', ['file' => $file]);

                continue;
            }

            activity()
                ->causedBy($user)
                ->withProperty('filename', $old->filename)
                ->log('Respaldo de base de datos eliminado por rotación');

            $old->delete();
        }

        DatabaseBackup::where('status', DatabaseBackup::STATUS_FAILED)
            ->where('id', '<', $latest->id)
            ->delete();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `C:/PHP83/php.exe artisan test --filter=DatabaseBackupServiceTest`
Expected: PASS (12 tests).

- [ ] **Step 5: Commit**

```bash
C:/PHP83/php.exe vendor/bin/pint app/Exceptions/DatabaseBackupException.php app/Services/DatabaseBackupService.php tests/Support/FakeDatabaseBackupService.php tests/Feature/DatabaseBackupServiceTest.php
git add app/Exceptions/DatabaseBackupException.php app/Services/DatabaseBackupService.php tests/Support/FakeDatabaseBackupService.php tests/Feature/DatabaseBackupServiceTest.php
git commit -m "feat: add database backup service with three-copy rotation"
```

---

### Task 3: Gate, rutas, controlador, vista y menú de perfil

**Files:**
- Modify: `app/Providers/AppServiceProvider.php:51-55` (bloque `Gate::before`)
- Create: `app/Http/Controllers/DatabaseBackupController.php`
- Modify: `routes/web.php` (bloque "Dev Tools", ~L820-823; agregar `use` arriba junto a los demás controladores)
- Create: `resources/views/db-backups/index.blade.php`
- Modify: `resources/views/layouts/partials/navbar.blade.php` (entre el item "Soporte" y `<div class="dropdown-divider"></div>`, ~L377-383)
- Test: `tests/Feature/DatabaseBackupAccessTest.php`

**Interfaces:**
- Consumes: `DatabaseBackupService::{isConfigured, localFileFor, create}`, `DatabaseBackupException`, `DatabaseBackup` (Tasks 1-2), `Tests\Support\FakeDatabaseBackupService`.
- Produces: Gate `manage-db-backups`; rutas `db-backups.index` (GET `/admin/db-backups`), `db-backups.store` (POST `/admin/db-backups`), `db-backups.download` (GET `/admin/db-backups/{databaseBackup}/download`); vista `db-backups.index` con variables `$backups`, `$configured`, `$keep`.

- [ ] **Step 1: Write the failing tests**

Crear `tests/Feature/DatabaseBackupAccessTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Models\DatabaseBackup;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\Support\FakeDatabaseBackupService;
use Tests\TestCase;

class DatabaseBackupAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckLockScreen::class);

        $this->backupDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'db-backups-'.uniqid();
        File::ensureDirectoryExists($this->backupDir);

        config([
            'db_backups.local_path' => $this->backupDir,
            'db_backups.sql_path' => '\\\\APP-SERVER\\portal-backups',
            'db_backups.keep' => 3,
            'db_backups.allowed_emails' => ['aldo.ochoa@totalgas.com', 'daniel.ramirez@totalgas.com'],
        ]);

        $this->app->instance(DatabaseBackupService::class, new FakeDatabaseBackupService);

        Role::findOrCreate('staff', 'web');
        Role::findOrCreate('superadmin', 'web');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupDir);

        parent::tearDown();
    }

    private function user(string $email, string $role = 'staff'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }

    private function storedBackup(string $status = DatabaseBackup::STATUS_COMPLETED, bool $withFile = true): DatabaseBackup
    {
        $backup = DatabaseBackup::create([
            'filename' => 'portal_20260923_100000.bak',
            'database_name' => 'portal',
            'size_bytes' => 4,
            'status' => $status,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        if ($withFile) {
            file_put_contents($this->backupDir.DIRECTORY_SEPARATOR.$backup->filename, 'data');
        }

        return $backup;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('db-backups.index'))->assertRedirect(route('login'));
    }

    public function test_unlisted_user_gets_403_everywhere(): void
    {
        $user = $this->user('otro@totalgas.com');
        $backup = $this->storedBackup();

        $this->actingAs($user)->get(route('db-backups.index'))->assertForbidden();
        $this->actingAs($user)->post(route('db-backups.store'))->assertForbidden();
        $this->actingAs($user)->get(route('db-backups.download', $backup))->assertForbidden();
        $this->assertSame(1, DatabaseBackup::count());
    }

    public function test_unlisted_superadmin_gets_403(): void
    {
        $admin = $this->user('jefe@totalgas.com', 'superadmin');

        $this->actingAs($admin)->get(route('db-backups.index'))->assertForbidden();
    }

    public function test_allowed_email_is_case_insensitive(): void
    {
        $user = $this->user('Aldo.Ochoa@TotalGas.com');

        $this->actingAs($user)->get(route('db-backups.index'))->assertOk();
    }

    public function test_allowed_user_generates_backup_and_sees_it_listed(): void
    {
        $user = $this->user('daniel.ramirez@totalgas.com');

        $this->actingAs($user)
            ->post(route('db-backups.store'))
            ->assertRedirect(route('db-backups.index'))
            ->assertSessionHas('success');

        $backup = DatabaseBackup::sole();

        $this->actingAs($user)->get(route('db-backups.index'))
            ->assertOk()
            ->assertSeeText($backup->filename)
            ->assertSeeText($backup->human_size)
            ->assertSeeText($user->name)
            ->assertSeeText('Generar respaldo');
    }

    public function test_store_shows_error_when_backup_fails(): void
    {
        $fake = new FakeDatabaseBackupService;
        $fake->failWith = new \RuntimeException('Cannot open backup device');
        $this->app->instance(DatabaseBackupService::class, $fake);

        $this->actingAs($this->user('aldo.ochoa@totalgas.com'))
            ->post(route('db-backups.store'))
            ->assertRedirect(route('db-backups.index'))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'Cannot open backup device'));
    }

    public function test_unconfigured_path_shows_notice_and_rejects_store(): void
    {
        config(['db_backups.sql_path' => null]);
        $user = $this->user('aldo.ochoa@totalgas.com');

        $this->actingAs($user)->get(route('db-backups.index'))
            ->assertOk()
            ->assertSee('DB_BACKUP_SQL_PATH')
            ->assertSee('id="btn-backup" disabled', false);

        $this->actingAs($user)->post(route('db-backups.store'))
            ->assertSessionHas('error');

        $this->assertSame(0, DatabaseBackup::count());
    }

    public function test_allowed_user_downloads_completed_backup(): void
    {
        $backup = $this->storedBackup();

        $this->actingAs($this->user('aldo.ochoa@totalgas.com'))
            ->get(route('db-backups.download', $backup))
            ->assertOk()
            ->assertDownload($backup->filename);
    }

    public function test_download_returns_404_for_failed_or_missing_file(): void
    {
        $user = $this->user('aldo.ochoa@totalgas.com');

        $failed = $this->storedBackup(DatabaseBackup::STATUS_FAILED);
        $this->actingAs($user)->get(route('db-backups.download', $failed))->assertNotFound();

        $failed->delete();
        $missing = $this->storedBackup(DatabaseBackup::STATUS_COMPLETED, withFile: false);
        $this->actingAs($user)->get(route('db-backups.download', $missing))->assertNotFound();
    }

    public function test_profile_menu_shows_link_only_to_allowed_users(): void
    {
        $this->actingAs($this->user('aldo.ochoa@totalgas.com'))
            ->get(route('dashboard'))
            ->assertSeeText('Respaldos de BD')
            ->assertSee(route('db-backups.index'), false);

        $this->actingAs($this->user('otro@totalgas.com'))
            ->get(route('dashboard'))
            ->assertDontSeeText('Respaldos de BD');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `C:/PHP83/php.exe artisan test --filter=DatabaseBackupAccessTest`
Expected: FAIL — `Route [db-backups.index] not defined`.

- [ ] **Step 3: Gate en `AppServiceProvider`**

Reemplazar el bloque actual:

```php
        Gate::before(function (\App\Models\User $user, string $ability) {
            if ($user->hasRole('superadmin')) {
                return true;
            }
        });
```

por:

```php
        Gate::before(function (\App\Models\User $user, string $ability) {
            // Los respaldos de BD se limitan a la lista de correos, incluso para superadmin.
            if ($ability === 'manage-db-backups') {
                return null;
            }

            if ($user->hasRole('superadmin')) {
                return true;
            }
        });

        Gate::define('manage-db-backups', function ($user): bool {
            return $user instanceof \App\Models\User
                && in_array(strtolower(trim((string) $user->email)), config('db_backups.allowed_emails', []), true);
        });
```

- [ ] **Step 4: Controlador**

`app/Http/Controllers/DatabaseBackupController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Exceptions\DatabaseBackupException;
use App\Models\DatabaseBackup;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DatabaseBackupController extends Controller
{
    public function __construct(private readonly DatabaseBackupService $backups) {}

    public function index(): View
    {
        return view('db-backups.index', [
            'backups' => DatabaseBackup::with('creator')->orderByDesc('id')->get(),
            'configured' => $this->backups->isConfigured(),
            'keep' => (int) config('db_backups.keep', 3),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $backup = $this->backups->create($request->user());
        } catch (DatabaseBackupException $e) {
            return redirect()->route('db-backups.index')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('db-backups.index')
                ->with('error', 'No se pudo generar el respaldo: '.Str::limit($e->getMessage(), 300));
        }

        return redirect()->route('db-backups.index')
            ->with('success', "Respaldo {$backup->filename} generado ({$backup->human_size}).");
    }

    public function download(DatabaseBackup $databaseBackup): BinaryFileResponse
    {
        abort_unless($databaseBackup->status === DatabaseBackup::STATUS_COMPLETED, 404);

        $file = $this->backups->localFileFor($databaseBackup);

        abort_unless(is_file($file), 404);

        return response()->download($file, $databaseBackup->filename);
    }
}
```

- [ ] **Step 5: Rutas**

En `routes/web.php`, añadir junto a los demás `use` de controladores:

```php
use App\Http\Controllers\DatabaseBackupController;
```

y justo debajo de la línea de `/dev/logs` (bloque "Dev Tools"):

```php
// ============================================================================
//  Respaldos de base de datos (solo correos en config('db_backups.allowed_emails'))
// ============================================================================
Route::middleware(['auth', 'lock', 'can:manage-db-backups'])
    ->prefix('admin/db-backups')
    ->name('db-backups.')
    ->group(function () {
        Route::get('/', [DatabaseBackupController::class, 'index'])->name('index');
        Route::post('/', [DatabaseBackupController::class, 'store'])->name('store');
        Route::get('/{databaseBackup}/download', [DatabaseBackupController::class, 'download'])->name('download');
    });
```

- [ ] **Step 6: Vista**

`resources/views/db-backups/index.blade.php`:

```blade
@extends('layouts.zircos')

@section('title', 'Respaldos de base de datos')
@section('page.title', 'Respaldos de base de datos')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Respaldos de BD</li>
@endsection

@section('content')
@unless($configured)
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="ti ti-alert-triangle fs-20"></i>
        <div>La ruta de respaldos no está configurada. Define <code>DB_BACKUP_SQL_PATH</code> en el <code>.env</code> para habilitar la generación.</div>
    </div>
@endunless

<div class="card shadow-sm border-0">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0"><i class="ti ti-database-export me-1 text-primary"></i>Respaldos de base de datos</h5>
            <small class="text-muted">Se conservan las {{ $keep }} copias más recientes; al generar una nueva se elimina la más antigua.</small>
        </div>
        <form method="POST" action="{{ route('db-backups.store') }}" id="backup-form">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm" id="btn-backup" @disabled(! $configured)>
                <i class="ti ti-database-plus me-1"></i>Generar respaldo
            </button>
        </form>
    </div>
    <div class="card-body p-0">
        @if($backups->isEmpty())
            <div class="text-center text-muted py-5"><i class="ti ti-database-off fs-48 d-block mb-2"></i>Aún no hay respaldos.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Archivo</th>
                            <th>Base de datos</th>
                            <th class="text-end">Peso</th>
                            <th>Fecha de creación</th>
                            <th>Generado por</th>
                            <th class="text-end">Duración</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($backups as $backup)
                            <tr>
                                <td><code>{{ $backup->filename }}</code></td>
                                <td>{{ $backup->database_name }}</td>
                                <td class="text-end">{{ $backup->human_size }}</td>
                                <td>{{ $backup->started_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
                                <td>{{ $backup->creator?->name ?? '—' }}</td>
                                <td class="text-end">{{ $backup->duration_seconds !== null ? $backup->duration_seconds.' s' : '—' }}</td>
                                <td>
                                    @switch($backup->status)
                                        @case(\App\Models\DatabaseBackup::STATUS_COMPLETED)
                                            <span class="badge bg-success-subtle text-success">Completado</span>
                                            @break
                                        @case(\App\Models\DatabaseBackup::STATUS_FAILED)
                                            <span class="badge bg-danger-subtle text-danger" title="{{ $backup->error_message }}">Fallido</span>
                                            <div class="small text-danger text-wrap" style="max-width: 320px;">{{ \Illuminate\Support\Str::limit($backup->error_message, 160) }}</div>
                                            @break
                                        @default
                                            <span class="badge bg-warning-subtle text-warning">En curso</span>
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    @if($backup->status === \App\Models\DatabaseBackup::STATUS_COMPLETED)
                                        <a href="{{ route('db-backups.download', $backup) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ti ti-download me-1"></i>Descargar
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <div class="card-footer text-muted small">
        Respaldo nativo de SQL Server (<code>COPY_ONLY</code>): no altera la cadena de respaldos programados del servidor.
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('backup-form')?.addEventListener('submit', function (event) {
        if (!confirm('¿Generar un nuevo respaldo? Si ya hay {{ $keep }}, se eliminará el más antiguo.')) {
            event.preventDefault();
            return;
        }
        const button = document.getElementById('btn-backup');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generando…';
    });
</script>
@endpush
```

- [ ] **Step 7: Entrada en el menú de perfil**

En `resources/views/layouts/partials/navbar.blade.php`, entre el item "Soporte" y `<div class="dropdown-divider"></div>`:

```blade
                        @unless($isSupplierGuard)
                            @can('manage-db-backups')
                                <!-- item-->
                                <a href="{{ route('db-backups.index') }}" class="dropdown-item">
                                    <i class="ti ti-database-export me-1 fs-17 align-middle"></i>
                                    <span class="align-middle">Respaldos de BD</span>
                                </a>
                            @endcan
                        @endunless
```

(`@unless($isSupplierGuard)` evita evaluar el Gate con un usuario proveedor, cuyo tipo no es `App\Models\User` y rompería el `Gate::before` tipado.)

- [ ] **Step 8: Run tests to verify they pass**

Run: `C:/PHP83/php.exe artisan test --filter=DatabaseBackup`
Expected: PASS (12 + 10 = 22 tests).

Si `test_profile_menu_shows_link_only_to_allowed_users` falla porque el dashboard exige algo extra para `staff`, revisar `DashboardRenderingTest::setUp` (crea todos los roles con `Role::findOrCreate`) y replicar lo necesario en este `setUp`; no cambiar la aserción.

- [ ] **Step 9: Commit**

```bash
C:/PHP83/php.exe vendor/bin/pint app/Providers/AppServiceProvider.php app/Http/Controllers/DatabaseBackupController.php routes/web.php tests/Feature/DatabaseBackupAccessTest.php
git add app/Providers/AppServiceProvider.php app/Http/Controllers/DatabaseBackupController.php routes/web.php resources/views/db-backups/index.blade.php resources/views/layouts/partials/navbar.blade.php tests/Feature/DatabaseBackupAccessTest.php
git commit -m "feat: add database backups page restricted to authorized users"
```

---

### Task 4: Verificación final

**Files:** ninguno nuevo.

- [ ] **Step 1: Suite completa**

Run: `C:/PHP83/php.exe artisan test`
Expected: la línea base es **36 fallos / 400 pasan** (2026-09-22). Resultado esperado: 36 fallos preexistentes y 422 pasan. Cualquier fallo nuevo fuera de `DatabaseBackup*` debe investigarse (probablemente el cambio a `Gate::before`).

- [ ] **Step 2: Chequeos de solo lectura**

Run: `C:/PHP83/php.exe artisan route:list --name=db-backups` → 3 rutas con middleware `can:manage-db-backups`.
Run: `C:/PHP83/php.exe artisan migrate --pretend` → solo muestra `create table [database_backups]`. **No** ejecutar `migrate` contra la BD compartida sin confirmación del usuario (ver AGENTS.md › Database Safety).
Run: `C:/PHP83/php.exe artisan view:cache` y luego `C:/PHP83/php.exe artisan view:clear` → sin errores de Blade.

- [ ] **Step 3: Verificación manual en ambiente real (la hace el usuario)**

Documentar en el PR y pedir al usuario:
1. `cguser` con rol `db_backupoperator` en la BD.
2. Compartir `storage/app/private/backups` del servidor de la app (p. ej. `\\SERVIDOR-APP\portal-backups`) con escritura para la cuenta de servicio de SQL Server.
3. `.env`: `DB_BACKUP_SQL_PATH=\\SERVIDOR-APP\portal-backups`.
4. `php artisan migrate` (tras verificar conexión destino).
5. Entrar con `aldo.ochoa@totalgas.com` → menú de perfil → "Respaldos de BD" → Generar; confirmar que el archivo aparece, el peso coincide con el `.bak` en disco y que se descarga. Generar 4 veces y confirmar que quedan 3.

Esto valida el único punto que las pruebas no cubren: que `runBackupStatement` con PDO_SQLSRV espere a que el BACKUP termine.

- [ ] **Step 4: Commit (si pint cambió algo)**

```bash
git status --short
# solo si hay cambios de formato:
git commit -am "style: format database backups files"
```
