<?php

use App\Cloud\Http\Controllers\BlogController;
use App\Cloud\Http\Controllers\ContactFormController;
use App\Cloud\Http\Controllers\SitemapController;
use App\Cloud\Http\Controllers\Tools\DeliverabilityTesterController;
use App\Cloud\Http\Controllers\Tools\EmailCheckerController;
use App\Cloud\Http\Controllers\Tools\SpfDmarcCheckerController;
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

Route::get('/pricing', fn () => config('eveil.edition') === 'cloud'
    ? view('marketing.pricing')
    : response('', 302, ['Location' => '/app']))->name('pricing');

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

// Free tools: DNS reads only, nothing sent, so a per-IP limit is enough.
Route::get('/tools/spf-dmarc-checker', fn () => config('eveil.edition') === 'cloud'
    ? view('marketing.tools.spf-dmarc')
    : response('', 302, ['Location' => '/app']))->name('tools.spf-dmarc');

Route::post('/tools/spf-dmarc-checker', SpfDmarcCheckerController::class)
    ->middleware('throttle:10,1')
    ->name('tools.spf-dmarc.check');

// No SMTP probe behind this one either, and a Turnstile check on top: an
// address checker is what list-cleaning scripts look for.
Route::get('/tools/email-checker', fn () => config('eveil.edition') === 'cloud'
    ? view('marketing.tools.email-checker')
    : response('', 302, ['Location' => '/app']))->name('tools.email-checker');

Route::post('/tools/email-checker', EmailCheckerController::class)
    ->middleware('throttle:10,1')
    ->name('tools.email-checker.check');

// Hands out a one-mail test address, behind Turnstile. The inbound route is
// called by the Email Routing worker, signed with a shared secret.
Route::get('/tools/deliverability-tester', fn () => config('eveil.edition') === 'cloud'
    ? view('marketing.tools.deliverability-tester')
    : response('', 302, ['Location' => '/app']))->name('tools.deliverability');

Route::post('/tools/deliverability-tester', [DeliverabilityTesterController::class, 'store'])
    ->middleware('throttle:5,60')
    ->name('tools.deliverability.store');

Route::post('/tools/deliverability-tester/inbound', [DeliverabilityTesterController::class, 'inbound'])
    ->name('tools.deliverability.inbound');

Route::get('/tools/deliverability-tester/{token}', [DeliverabilityTesterController::class, 'show'])
    ->where('token', '[a-z0-9]{16}')
    ->name('tools.deliverability.show');

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
