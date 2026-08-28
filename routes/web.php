<?php

use Illuminate\Support\Facades\Route;

/*
 * Public site. Plain Blade, no Inertia: the application itself lives under
 * the `/app` prefix in routes/app.php. A self-hosted instance has no product
 * to present, so every marketing page skips straight to the application.
 */
// Checked per request, not at boot: config() can change at runtime (tests
// flip `eveil.edition` this way), and this must still answer correctly under
// `route:cache`, which freezes the closures below but not what they read.
//
// A RELATIVE Location, resolved by the browser against the address it
// actually used. An absolute one is built from what the server believes
// about itself, which behind a proxy or on a published non-standard port
// is how somebody typing `host:8099` gets sent to `host` and finds
// nothing answering.
Route::get('/', fn () => config('eveil.edition') === 'cloud'
    ? view('marketing.home')
    : response('', 302, ['Location' => '/app']))->name('home');

Route::get('/privacy', fn () => config('eveil.edition') === 'cloud'
    ? view('marketing.privacy')
    : response('', 302, ['Location' => '/app']))->name('privacy');

Route::get('/terms', fn () => config('eveil.edition') === 'cloud'
    ? view('marketing.terms')
    : response('', 302, ['Location' => '/app']))->name('terms');

Route::get('/data-retention', fn () => config('eveil.edition') === 'cloud'
    ? view('marketing.data-retention')
    : response('', 302, ['Location' => '/app']))->name('data-retention');

Route::get('/contact', fn () => config('eveil.edition') === 'cloud'
    ? view('marketing.contact')
    : response('', 302, ['Location' => '/app']))->name('contact');
