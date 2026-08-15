<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FlushSessions extends Command
{
    protected $signature = 'app:flush-sessions';

    protected $description = 'Hapus semua sesi login dan token "ingat saya" (dipakai desktop: wajib login ulang tiap buka aplikasi)';

    public function handle(): int
    {
        $table = config('session.table', 'sessions');

        DB::table($table)->delete();
        DB::table('users')->whereNotNull('remember_token')->update(['remember_token' => null]);

        $this->info('Semua sesi login dan token "ingat saya" telah dihapus.');

        return self::SUCCESS;
    }
}
