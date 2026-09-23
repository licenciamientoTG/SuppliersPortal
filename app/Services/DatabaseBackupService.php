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
            $this->failStaleRunningBackups($timeout);

            return $this->runBackup($user, $timeout);
        } finally {
            $lock->release();
        }
    }

    /**
     * Si IIS/FastCGI mata la petición a mitad del BACKUP, el bloque finally nunca
     * corre y la fila se queda en "running" para siempre. Antes de iniciar un nuevo
     * respaldo, se marcan como fallidas las filas "running" cuyo tiempo de espera
     * ya expiró.
     */
    private function failStaleRunningBackups(int $timeout): void
    {
        $stale = DatabaseBackup::where('status', DatabaseBackup::STATUS_RUNNING)
            ->where('started_at', '<', now()->subSeconds($timeout))
            ->get();

        foreach ($stale as $backup) {
            $file = $this->localFileFor($backup);

            if (is_file($file)) {
                @unlink($file);
            }

            $backup->update([
                'status' => DatabaseBackup::STATUS_FAILED,
                'error_message' => 'El proceso se interrumpió antes de terminar (tiempo de espera del servidor web).',
                'finished_at' => now(),
            ]);
        }
    }

    /**
     * Ejecuta el BACKUP en SQL Server. Con PDO_SQLSRV el comando emite varios
     * mensajes informativos; hay que consumir todos los rowsets o la llamada
     * regresa antes de que el respaldo termine.
     */
    protected function runBackupStatement(string $sql, string $expectedLocalFile): void
    {
        $pdo = DB::connection()->getPdo();

        $options = defined('PDO::SQLSRV_ATTR_DIRECT_QUERY')
            ? [\PDO::SQLSRV_ATTR_DIRECT_QUERY => true, \PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 0]
            : [];

        $statement = $pdo->prepare($sql, $options);
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

            try {
                activity()
                    ->performedOn($backup)
                    ->causedBy($user)
                    ->withProperty('error', $e->getMessage())
                    ->log('Respaldo de base de datos fallido');
            } catch (Throwable $e2) {
                // No dejar que un fallo al registrar la actividad enmascare la excepción original.
                report($e2);
            }

            throw $e;
        }

        activity()
            ->performedOn($backup)
            ->causedBy($user)
            ->withProperty('size_bytes', $backup->size_bytes)
            ->log('Respaldo de base de datos generado');

        try {
            $this->prune($backup, $user);
        } catch (Throwable $e) {
            // Un fallo en la rotación no debe reportarse como un respaldo fallido:
            // el respaldo ya se completó con éxito.
            report($e);
        }

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
