<?php

use App\Http\Controllers\Account\AccountDeletionController;
use App\Http\Controllers\Account\TwoFactorController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\CurrentProjectController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectKnowledgeBaseController;
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

Route::middleware(['auth', 'project.set'])->group(function (): void {
    /*
     * Switching projects and creating one are the two things reachable without
     * a project already selected — everything else would have nothing to show.
     */
    Route::put('current-project/{project}', [CurrentProjectController::class, 'update'])
        ->middleware('can:view,project')
        ->name('current-project.update');

    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');

    Route::middleware('project.require')->group(function (): void {
        Route::inertia('/', 'Dashboard')->name('dashboard');

        /*
         * The current project comes from the session, so none of these carry it
         * in the URL: switching projects leaves you on the page you were on.
         */
        Route::prefix('settings')->name('settings.')->group(function (): void {
            Route::redirect('/', '/app/settings/project');

            Route::get('project', [ProjectController::class, 'edit'])->name('project.edit');
            Route::put('project', [ProjectController::class, 'update'])->name('project.update');
            Route::delete('project', [ProjectController::class, 'destroy'])->name('project.destroy');

            Route::get('knowledge-base', [ProjectKnowledgeBaseController::class, 'edit'])
                ->name('knowledge-base.edit');
            Route::put('knowledge-base', [ProjectKnowledgeBaseController::class, 'update'])
                ->name('knowledge-base.update');
        });
    });

    /*
     * Account management. The forms post to Fortify's own update routes, so
     * most of these only need to render a page. Deliberately outside
     * `project.require`: somebody with no project still has an account.
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
