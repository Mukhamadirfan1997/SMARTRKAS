<?php

namespace App\Listeners;

use App\Jobs\SendTelegramNotificationJob;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\CleanupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

class NotifyBackupTelegram
{
    public function handle(object $event): void
    {
        match (true) {
            $event instanceof BackupWasSuccessful => $this->handleSuccess($event),
            $event instanceof BackupHasFailed => $this->handleFailed($event),
            $event instanceof CleanupWasSuccessful => $this->handleCleanupSuccess($event),
            $event instanceof CleanupHasFailed => $this->handleCleanupFailed($event),
            $event instanceof HealthyBackupWasFound => $this->handleHealthy($event),
            $event instanceof UnhealthyBackupWasFound => $this->handleUnhealthy($event),
            default => null,
        };
    }

    protected function handleSuccess(BackupWasSuccessful $event): void
    {
        $dest = $event->backupDestination;
        $size = $dest->newestBackup()?->sizeInBytes();
        $sizeText = $size ? round($size / 1048576, 2) . ' MB' : 'unknown';
        $disk = $dest->diskName();
        $backupName = $dest->backupName();

        SendTelegramNotificationJob::dispatch(
            'INFO',
            "Backup berhasil\nDisk: {$disk}\nUkuran: {$sizeText}\nPath: {$backupName}"
        );
    }

    protected function handleFailed(BackupHasFailed $event): void
    {
        $exception = $event->exception;
        $message = $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();

        SendTelegramNotificationJob::dispatch(
            'ERROR',
            "Backup GAGAL!\nError: {$message}\nFile: {$file}:{$line}"
        );
    }

    protected function handleCleanupSuccess(CleanupWasSuccessful $event): void
    {
        $disk = $event->backupDestination->diskName();
        SendTelegramNotificationJob::dispatch(
            'INFO',
            "Pembersihan backup berhasil\nDisk: {$disk}"
        );
    }

    protected function handleCleanupFailed(CleanupHasFailed $event): void
    {
        $exception = $event->exception;
        SendTelegramNotificationJob::dispatch(
            'ERROR',
            "Pembersihan backup GAGAL!\nError: " . $exception->getMessage()
        );
    }

    protected function handleHealthy(HealthyBackupWasFound $event): void
    {
        $status = $event->backupDestinationStatus;
        $backupDest = $status->backupDestination();
        $disk = $backupDest->diskName();
        $count = $backupDest->backups()->count();

        SendTelegramNotificationJob::dispatch(
            'INFO',
            "Backup sehat\nDisk: {$disk}\nJumlah backup: {$count}"
        );
    }

    protected function handleUnhealthy(UnhealthyBackupWasFound $event): void
    {
        $status = $event->backupDestinationStatus;
        $backupDest = $status->backupDestination();
        $disk = $backupDest->diskName();
        $failure = $status->getHealthCheckFailure();
        $healthChecks = $failure ? $failure->exception()->getMessage() : 'Unknown error';

        SendTelegramNotificationJob::dispatch(
            'WARNING',
            "Backup TIDAK SEHAT!\nDisk: {$disk}\n{$healthChecks}"
        );
    }
}
