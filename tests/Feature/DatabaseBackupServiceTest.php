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
