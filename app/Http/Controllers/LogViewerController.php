<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class LogViewerController extends Controller
{
    private string $logPath;

    public function __construct()
    {
        $this->logPath = storage_path('logs/laravel.log');
    }

    public function index()
    {
        abort_unless(Auth::user()?->hasRole('superadmin'), 403);

        $lines = [];

        if (file_exists($this->logPath)) {
            $file = new \SplFileObject($this->logPath, 'r');
            $file->seek(PHP_INT_MAX);
            $total = $file->key();
            $start = max(0, $total - 500);
            $content = [];

            $file->seek($start);
            while (! $file->eof()) {
                $content[] = $file->fgets();
            }

            $lines = implode('', $content);
        }

        $fileSize = file_exists($this->logPath)
            ? round(filesize($this->logPath) / 1024, 1).' KB'
            : '0 KB';

        return view('dev.log-viewer', compact('lines', 'fileSize'));
    }
}
