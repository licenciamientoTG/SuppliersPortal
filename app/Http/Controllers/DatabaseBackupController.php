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

        @set_time_limit((int) config('db_backups.timeout', 1800));

        return response()->download($file, $databaseBackup->filename);
    }
}
