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
