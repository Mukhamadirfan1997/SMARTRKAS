<?php

namespace App\Support;

use Dotenv\Dotenv;

/**
 * Muat konfigurasi tambahan dari folder data aplikasi (mode desktop).
 *
 * Di mode desktop, file .env bawaan ter-bundle di folder instalasi yang
 * bersifat read-only. Agar pengguna bisa mengisi token bot tanpa menyentuh
 * file itu, aplikasi juga membaca <DataDir>/.env (folder data aplikasi).
 * Nilai di file ini MENIMPA env yang sudah ada.
 */
final class DataDirEnv
{
    public static function load(string $dataDir): void
    {
        $envFile = rtrim($dataDir, '/\\').DIRECTORY_SEPARATOR.'.env';

        if (! is_file($envFile)) {
            return;
        }

        Dotenv::createMutable(rtrim($dataDir, '/\\'), '.env')->safeLoad();
    }
}
