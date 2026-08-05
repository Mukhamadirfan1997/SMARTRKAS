<?php

namespace App\Services;

use App\Exceptions\RestoreException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use SplFileInfo;
use ZipArchive;

class RestoreService
{
    public function __construct(
        private Filesystem $files,
        private bool $disconnectBeforeSwap = true,
    ) {
    }

    /**
     * Ekstrak arsip backup, temukan database SQLite di dalamnya, lalu
     * timpa database aktif.
     *
     * @throws RestoreException
     */
    public function restore(string $zipPath, ?string $targetPath = null, ?string $workingDir = null): void
    {
        if (! $this->files->isFile($zipPath)) {
            throw new RestoreException('File backup tidak ditemukan.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RestoreException('File yang diunggah bukan arsip backup (.zip) yang valid.');
        }
        $zipOpened = true;

        $dir = $workingDir ?? storage_path('app/restore-temp').DIRECTORY_SEPARATOR.(string) Str::uuid();

        try {
            $this->files->makeDirectory($dir, 0755, true, true);
            if (! $zip->extractTo($dir)) {
                throw new RestoreException('Arsip backup tidak dapat diekstrak.');
            }
            $zip->close();
            $zipOpened = false;

            $dbFile = $this->locateDatabase($dir);
            if ($dbFile === null) {
                throw new RestoreException('Backup tidak berisi database SmartRKAS yang valid.');
            }

            $this->replaceDatabase($dbFile, $targetPath);
        } finally {
            if ($zipOpened) {
                $zip->close();
            }
            $this->files->deleteDirectory($dir);
        }
    }

    private function locateDatabase(string $dir): ?string
    {
        $files = $this->files->allFiles($dir);

        $candidates = array_filter($files, function (SplFileInfo $file): bool {
            $extension = strtolower($file->getExtension());
            $path = strtolower($file->getPathname());

            return in_array($extension, ['sqlite', 'db', 'sqlite3'], true)
                || str_contains($path, 'db-dumps')
                || str_contains($path, 'database');
        });

        foreach ($candidates as $candidate) {
            if ($this->isValidDatabase($candidate->getPathname())) {
                return $candidate->getPathname();
            }
        }

        foreach ($files as $file) {
            if ($file->getSize() < 16) {
                continue;
            }
            if ($this->isValidDatabase($file->getPathname())) {
                return $file->getPathname();
            }
        }

        return null;
    }

    private function isValidDatabase(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        if ($header === false || strcmp($header, "SQLite format 3\0") !== 0) {
            return false;
        }

        try {
            $pdo = new PDO('sqlite:'.$path);
            $statement = $pdo->query(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name IN ('users', 'migrations')"
            );
            if ($statement === false) {
                $pdo = null;

                return false;
            }
            $tables = $statement->fetchAll(PDO::FETCH_COLUMN);
            $pdo = null;

            return count($tables) >= 2;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Salin file database baru menimpa database aktif. File lama disimpan
     * sebagai <nama>.pre-restore sebagai pengaman.
     *
     * @throws RestoreException
     */
    public function replaceDatabase(string $newDbPath, ?string $targetPath = null): void
    {
        $connection = (string) config('database.default');
        $target = $targetPath ?? (string) config('database.connections.'.$connection.'.database');

        if ($target === '' || $target === ':memory:') {
            throw new RestoreException('Lokasi database aktif tidak valid untuk dipulihkan.');
        }

        if ($this->disconnectBeforeSwap) {
            DB::disconnect();
        }

        if ($this->files->exists($target)) {
            $this->files->copy($target, $target.'.pre-restore');
        }

        $this->files->ensureDirectoryExists(dirname($target));
        $this->files->copy($newDbPath, $target);
    }
}
