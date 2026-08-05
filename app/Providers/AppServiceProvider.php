<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $events = [
            \Spatie\Backup\Events\BackupWasSuccessful::class,
            \Spatie\Backup\Events\BackupHasFailed::class,
            \Spatie\Backup\Events\CleanupWasSuccessful::class,
            \Spatie\Backup\Events\CleanupHasFailed::class,
            \Spatie\Backup\Events\HealthyBackupWasFound::class,
            \Spatie\Backup\Events\UnhealthyBackupWasFound::class,
        ];

        foreach ($events as $event) {
            Event::listen($event, \App\Listeners\NotifyBackupTelegram::class);
        }
    }
}
