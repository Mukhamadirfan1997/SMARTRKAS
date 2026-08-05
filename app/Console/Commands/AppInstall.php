<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AppInstall extends Command
{
    protected $signature = 'app:install';

    protected $description = 'Siapkan database SmartRKAS (migrasi + seed master data) untuk pertama kali';

    public function handle(): int
    {
        if (! $this->confirmInstallation()) {
            return self::FAILURE;
        }

        $this->info('Menjalankan migrasi database...');
        $this->callSilent('migrate', ['--force' => true]);

        $this->info('Mengisi master data awal...');
        $this->callSilent('db:seed', ['--force' => true]);

        $this->info('SmartRKAS siap digunakan.');

        return self::SUCCESS;
    }

    private function confirmInstallation(): bool
    {
        if (app()->runningInConsole() && getenv('SMARTRKAS_DATA_DIR')) {
            return true;
        }

        return $this->confirm('Instal database SmartRKAS sekarang?');
    }
}
