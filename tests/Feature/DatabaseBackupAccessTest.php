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
