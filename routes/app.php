<?php

use App\Http\Controllers\Account\AccountDeletionController;
use App\Http\Controllers\Account\TwoFactorController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

/*
 * The Inertia application. Everything here is prefixed with `/app` by
 * bootstrap/app.php, and Fortify's auth routes are prefixed to match
 * (config/fortify.php).
 */

Route::middleware('guest')->group(function (): void {
    Route::get('setup', [SetupController::class, 'create'])->name('setup');
    Route::post('setup', [SetupController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::inertia('/', 'Dashboard')->name('dashboard');

    /*
     * Creating and editing happen in a dialog on the index, so there is no
     * `create` or `edit` screen to route to.
     *
     * Authorisation is middleware rather than a call inside the controller so
     * that it runs BEFORE the form request: validating first would have a
     * stranger's payload fetch a URL of their choosing on the way to being
     * told the project does not exist.
     */
    Route::resource('projects', ProjectController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('update', 'can:update,project')
        ->middlewareFor('destroy', 'can:delete,project');

    /*
     * Account management. The forms post to Fortify's own update routes, so
     * most of these only need to render a page.
     */
    Route::prefix('account')->name('account.')->group(function (): void {
        Route::redirect('/', '/app/account/profile');

        Route::inertia('profile', 'account/Profile')->name('profile');
        Route::inertia('password', 'account/Password')->name('password');
        Route::get('two-factor', [TwoFactorController::class, 'edit'])->name('two-factor');
        Route::inertia('delete', 'account/Delete')->name('delete');

        Route::delete('/', [AccountDeletionController::class, 'destroy'])->name('destroy');
    });
});
