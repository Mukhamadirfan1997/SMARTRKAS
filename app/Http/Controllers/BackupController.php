<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    private string $backupDir;

    public function __construct()
    {
        $this->backupDir = (string) config('backup.backup.name', 'laravel-backup');
    }

    public function index(): View
    {
        $backups = collect(Storage::disk('local')->files($this->backupDir))
            ->filter(fn (string $file): bool => str_ends_with($file, '.zip'))
            ->map(function (string $file): array {
                return [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => Storage::disk('local')->size($file),
                    'mtime' => Storage::disk('local')->lastModified($file),
                ];
            })
            ->sortByDesc('mtime')
            ->values();

        $totalSize = $backups->sum('size');
        $latest = $backups->first();

        return view('pengaturan.backup', compact('backups', 'totalSize', 'latest'));
    }

    public function run(): RedirectResponse
    {
        try {
            Artisan::call('backup:run');
        } catch (\Throwable $e) {
            return back()->with('error', 'Backup gagal: '.$e->getMessage());
        }

        return back()->with('success', 'Backup berhasil dibuat.');
    }

    public function download(string $file): StreamedResponse
    {
        $name = basename($file);

        if ($name !== $file || ! str_ends_with($name, '.zip')) {
            abort(404);
        }

        $path = $this->backupDir.'/'.$name;

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->download($path, $name);
    }
}
