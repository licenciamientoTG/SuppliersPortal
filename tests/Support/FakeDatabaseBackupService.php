<?php

namespace Tests\Support;

use App\Services\DatabaseBackupService;

class FakeDatabaseBackupService extends DatabaseBackupService
{
    /** @var string[] */
    public array $statements = [];

    /** Falla al ejecutar el BACKUP (antes de copiar). */
    public ?\Throwable $failWith = null;

    /** Falla a mitad de la copia, dejando un archivo parcial. */
    public ?\Throwable $copyFailWith = null;

    public bool $writeFile = true;

    public string $contents = 'fake-bak-contents';

    public ?string $defaultPath = 'C:\\SQL\\Backup';

    protected function defaultServerBackupPath(): ?string
    {
        return $this->defaultPath;
    }

    protected function runBackupStatement(string $sql): void
    {
        $this->statements[] = $sql;

        if ($this->failWith) {
            throw $this->failWith;
        }
    }

    protected function copyServerFile(string $readSql, string $localFile): void
    {
        $this->statements[] = $readSql;

        if ($this->writeFile) {
            file_put_contents($localFile, $this->contents);
        }

        if ($this->copyFailWith) {
            throw $this->copyFailWith;
        }
    }
}
