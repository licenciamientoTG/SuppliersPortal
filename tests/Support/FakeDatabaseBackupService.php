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
