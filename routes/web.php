<?php

use App\Cloud\Http\Controllers\BlogController;
use App\Cloud\Http\Controllers\ContactFormController;
use App\Cloud\Http\Controllers\SitemapController;
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

Route::post('/contact', [ContactFormController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{article}/{slug?}', [BlogController::class, 'show'])->whereNumber('article')->name('blog.show');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// A route rather than `public/robots.txt`, because the Sitemap line only
// makes sense on the edition that has one: a self-hosted instance pointing a
// crawler at its own /sitemap.xml would be pointing it at a 404.
Route::get('/robots.txt', fn () => response(
    "User-agent: *\nDisallow:\n".(config('eveil.edition') === 'cloud' ? 'Sitemap: '.route('sitemap')."\n" : ''),
    200,
    ['Content-Type' => 'text/plain']
))->name('robots');
