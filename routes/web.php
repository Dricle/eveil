<?php

use Illuminate\Support\Facades\Route;

/*
 * Public site. Plain Blade, no Inertia — the application itself lives under
 * the `/app` prefix in routes/app.php. A self-hosted instance has no product
 * to present, so it skips straight to the application.
 */
Route::get('/', function () {
    if (config('eveil.edition') !== 'cloud') {
        return redirect('/app');
    }

    return view('marketing.home');
})->name('home');
