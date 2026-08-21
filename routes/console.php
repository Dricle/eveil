<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Horizon's metrics page stays empty without this. Running the workers does
// not record throughput on its own.
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Sending is paced, never bursty: each tick queues at most one mail per mailbox
// and stops. Five minutes is what turns a daily allowance into mail that leaves
// the way a person's does, and nothing goes out outside the sending window,
// which the action itself enforces rather than the schedule.
Schedule::command('eveil:send-due')->everyFiveMinutes();

// A campaign that only looked once, at the moment it was started, leaves every
// contact found afterwards outside it for good. Discovery lands in waves, so
// afterwards is the normal case. Cheap to repeat: this is pure SQL, and it
// refuses anybody already in a live sequence.
Schedule::command('eveil:enrol-due')->everyFiveMinutes();

// A segment with no sequence is one the searches keep filling with companies
// nobody will ever be written to. Hourly, and only where the user asked not to
// be involved: writing three mails is the most expensive call the product
// makes, and segments appear once or twice in a project's life.
Schedule::command('eveil:write-missing')->hourly();

// Replies are the only opt-out channel and the only metric there is, so the
// inbox is read on a rhythm of its own. A mailbox that stopped sending still
// receives the answers to what already went out.
Schedule::command('eveil:fetch-replies')->everyFiveMinutes();
