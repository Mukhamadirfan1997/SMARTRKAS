<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jadwal sengaja di jam kerja (bukan tengah malam): scheduler desktop hanya berjalan
// selama aplikasi terbuka, jadi jadwal jam 01:00-04:00 nyaris tidak pernah tereksekusi.
Schedule::command('backup:run')->daily()->at('20:15');
Schedule::command('backup:clean')->daily()->at('20:00');
Schedule::command('audit:clean 90')->weekly()->sundays()->at('20:30');
Schedule::call(function () {
    DB::table('failed_jobs')
        ->where('failed_at', '<', now()->subDays(30))
        ->delete();
})->weekly()->sundays()->at('20:35');
Schedule::command('kwitansi:clean 2')->monthly()->at('20:40');
Schedule::command('telegram:kwitansi-reminder')->weekly()->mondays()->at('08:00');
Schedule::command('telegram:realisasi-warning')->monthlyOn(25, '09:00');
