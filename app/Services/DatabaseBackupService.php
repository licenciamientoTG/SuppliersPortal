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

    /**
     * Archivo temporal en el servidor SQL. Siempre el mismo nombre: INIT lo
     * sobrescribe en cada respaldo, así que en el servidor nunca se acumulan copias.
     */
    private const SERVER_FILENAME = 'suppliers_portal_backup.bak';

    public function localPath(): string
    {
        return rtrim((string) config('db_backups.local_path'), '\\/');
    }

    public function localFileFor(DatabaseBackup $backup): string
    {
        return $this->localPath().DIRECTORY_SEPARATOR.basename($backup->filename);
    }

    /**
     * Ruta del .bak temporal tal como la ve SQL Server: DB_BACKUP_SQL_PATH si
     * está definida, o la carpeta de respaldos predeterminada de la instancia.
     */
    public function serverBackupFile(): string
    {
        $directory = filled(config('db_backups.sql_path'))
            ? (string) config('db_backups.sql_path')
            : $this->defaultServerBackupPath();

        if (blank($directory)) {
            throw new DatabaseBackupException(
                'No se pudo determinar la carpeta de respaldos de SQL Server. Define DB_BACKUP_SQL_PATH en el .env.'
            );
        }

        return rtrim($directory, '\\/').'\\'.self::SERVER_FILENAME;
    }

    public function buildBackupSql(string $database, string $serverFile): string
    {
        return sprintf(
            "BACKUP DATABASE [%s] TO DISK = N'%s' WITH COPY_ONLY, INIT, CHECKSUM, NAME = N'Portal manual backup'",
            str_replace(']', ']]', $database),
            str_replace("'", "''", $serverFile)
        );
    }

    public function buildReadSql(string $serverFile): string
    {
        return sprintf(
            "SELECT BulkColumn FROM OPENROWSET(BULK N'%s', SINGLE_BLOB) AS backup_file",
            str_replace("'", "''", $serverFile)
        );
    }

    public function create(User $user): DatabaseBackup
    {
        $timeout = (int) config('db_backups.timeout', 1800);
        $lock = Cache::lock(self::LOCK_KEY, $timeout);

        if (! $lock->get()) {
            throw new DatabaseBackupException('Ya hay un respaldo en curso. Intenta de nuevo en unos minutos.');
        }

        try {
            $this->failStaleRunningBackups($timeout);

            return $this->runBackup($user, $timeout, $this->serverBackupFile());
        } finally {
            $lock->release();
        }
    }

    /**
     * Si el servidor web mata la petición a mitad del BACKUP, el bloque finally nunca
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

    protected function defaultServerBackupPath(): ?string
    {
        $row = DB::selectOne("SELECT CAST(SERVERPROPERTY('InstanceDefaultBackupPath') AS nvarchar(4000)) AS path");

        return $row?->path;
    }

    /**
     * Ejecuta el BACKUP en SQL Server. Con PDO_SQLSRV el comando emite varios
     * mensajes informativos; hay que consumir todos los rowsets o la llamada
     * regresa antes de que el respaldo termine.
     */
    protected function runBackupStatement(string $sql): void
    {
        $statement = DB::connection()->getPdo()->prepare($sql, $this->sqlsrvStatementOptions());
        $statement->execute();

        do {
            // Consumir mensajes "Processed N pages..." hasta el final.
        } while ($statement->nextRowset());
    }

    /**
     * Lee el .bak del disco del servidor SQL a través de la conexión y lo escribe
     * en la carpeta local por bloques (stream), sin cargarlo completo en memoria.
     */
    protected function copyServerFile(string $readSql, string $localFile): void
    {
        $statement = DB::connection()->getPdo()->prepare($readSql, $this->sqlsrvStatementOptions());
        $statement->execute();

        $content = null;
        $statement->bindColumn(1, $content, \PDO::PARAM_LOB, 0, \PDO::SQLSRV_ENCODING_BINARY);

        if (! $statement->fetch(\PDO::FETCH_BOUND)) {
            throw new DatabaseBackupException('SQL Server no devolvió el archivo de respaldo.');
        }

        $target = fopen($localFile, 'wb');

        try {
            if (is_resource($content)) {
                stream_copy_to_stream($content, $target);
            } else {
                fwrite($target, (string) $content);
            }
        } finally {
            fclose($target);
            $statement->closeCursor();
        }
    }

    private function sqlsrvStatementOptions(): array
    {
        return defined('PDO::SQLSRV_ATTR_DIRECT_QUERY')
            ? [\PDO::SQLSRV_ATTR_DIRECT_QUERY => true, \PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 0]
            : [];
    }

    private function runBackup(User $user, int $timeout, string $serverFile): DatabaseBackup
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

            $this->runBackupStatement($this->buildBackupSql($database, $serverFile));
            $this->copyServerFile($this->buildReadSql($serverFile), $localFile);

            clearstatcache(true, $localFile);

            if (! is_file($localFile) || filesize($localFile) === 0) {
                throw new DatabaseBackupException(
                    'SQL Server generó el respaldo pero no se pudo copiar a '.$this->localPath().'.'
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
