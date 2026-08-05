<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class CleanAuditLog extends Command
{
    protected $signature = 'audit:clean {days=90 : Hapus log lebih dari N hari}';

    protected $description = 'Hapus audit log yang lebih lama dari N hari';

    public function handle(): void
    {
        $days = (int) $this->argument('days');
        $cutoff = now()->subDays($days);

        $deleted = AuditLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} audit log entries older than {$days} days.");
    }
}
