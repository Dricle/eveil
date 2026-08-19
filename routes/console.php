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

// Sending is paced, never bursty: each tick queues at most one mail per mailbox
// and stops. Five minutes is what turns a daily allowance into mail that leaves
// the way a person's does — and nothing goes out outside the sending window,
// which the action itself enforces rather than the schedule.
Schedule::command('eveil:send-due')->everyFiveMinutes();

// Replies are the only opt-out channel and the only metric there is, so the
// inbox is read on a rhythm of its own — a mailbox that stopped sending still
// receives the answers to what already went out.
Schedule::command('eveil:fetch-replies')->everyFiveMinutes();
