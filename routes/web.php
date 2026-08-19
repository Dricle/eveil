<?php

use Illuminate\Support\Facades\Route;

/*
 * Public site. Plain Blade, no Inertia — the application itself lives under
 * the `/app` prefix in routes/app.php. A self-hosted instance has no product
 * to present, so it skips straight to the application.
 */
Route::get('/', function () {
    if (config('eveil.edition') !== 'cloud') {
        // A RELATIVE Location, resolved by the browser against the address it
        // actually used. An absolute one is built from what the server believes
        // about itself, which behind a proxy or on a published non-standard port
        // is how somebody typing `host:8099` gets sent to `host` and finds
        // nothing answering.
        return response('', 302, ['Location' => '/app']);
    }

    return view('marketing.home');
})->name('home');
