<?php

namespace App\Support;

use PDO;
use Spatie\DbDumper\Databases\Sqlite;

/**
 * Custom SQLite dumper that uses PDO + file copy instead of shelling out to sqlite3.exe.
 *
 * The default spatie Sqlite dumper requires the sqlite3 CLI binary, which is not
 * available in bundled desktop deployments. This class uses PHP's PDO extension
 * (always bundled with PHP) to flush WAL, then copies the database file directly.
 */
class PdoSqliteDumper extends Sqlite
{
    public function dumpToFile(string $dumpFile): void
    {
        $dbName = $this->getDbName();

        $this->checkpointWal($dbName);

        if (! @copy($dbName, $dumpFile)) {
            $errors = error_get_last();
            $message = 'Failed to copy database file to dump location.';
            if ($errors !== null) {
                $message .= ' PHP error: '.$errors['message'];
            }
            $message .= " Source: {$dbName}, Destination: {$dumpFile}";

            throw new \RuntimeException($message);
        }

        if (! file_exists($dumpFile) || filesize($dumpFile) === 0) {
            throw new \RuntimeException("Dump file was not created or is empty: {$dumpFile}");
        }
    }

    private function checkpointWal(string $dbName): void
    {
        try {
            $pdo = new PDO('sqlite:'.$dbName);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $pdo = null;
        } catch (\Throwable) {
            // WAL checkpoint is best-effort. If it fails (e.g. locked database),
            // the file copy will still produce a usable backup.
        }
    }
}
