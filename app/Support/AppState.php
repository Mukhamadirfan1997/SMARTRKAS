<?php

namespace App\Support;

use App\Models\User;

final class AppState
{
    /**
     * Lokasi marker "aplikasi sudah diinisialisasi". Dipakai karena session
     * & cache tersimpan di database SQLite sehingga hilang saat restore
     * menukar file database.
     */
    public static function markerPath(): string
    {
        $dataDir = config('app.data_dir');

        if (is_string($dataDir) && $dataDir !== '') {
            return rtrim($dataDir, '/\\').DIRECTORY_SEPARATOR.'.app-initialized';
        }

        return (string) config('app.initialized_marker_path', storage_path('app/private/.app-initialized'));
    }

    /**
     * True saat aplikasi belum pernah dipakai: belum ada user yang pernah
     * login dan belum pernah selesai melakukan restore.
     */
    public static function isFirstRun(): bool
    {
        if (file_exists(self::markerPath())) {
            return false;
        }

        return User::query()->whereNotNull('last_login_at')->doesntExist();
    }

    public static function initialize(): void
    {
        $path = self::markerPath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, now()->toDateTimeString());
    }
}
