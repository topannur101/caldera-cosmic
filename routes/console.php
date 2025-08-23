<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();

Schedule::command('app:ins-omv-cleanup')->daily();
Schedule::command('app:inv-empty-resolved')->daily();
Schedule::command('app:sync-user-prefs')->everyFiveMinutes();
