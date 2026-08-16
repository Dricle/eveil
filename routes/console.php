<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Horizon's metrics page stays empty without this — running the workers does
// not record throughput on its own.
Schedule::command('horizon:snapshot')->everyFiveMinutes();
