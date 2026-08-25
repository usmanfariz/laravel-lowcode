<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tugas Terjadwal
|--------------------------------------------------------------------------
|
| Butuh satu entri cron di server:
|     * * * * * cd /path/ke/proyek && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Berkas ekspor sebagian besar hanya diunduh sekali; menyimpannya lebih dari
// seminggu tidak ada gunanya dan membuat storage membengkak.
Schedule::command('exports:prune --days=7')
    ->dailyAt('02:00')
    ->onOneServer();
