<?php

use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\SecurityController;
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

    Route::get('security', [SecurityController::class, 'edit'])->name('security');
});
