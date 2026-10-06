<?php

use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CampaignEnrolmentController;
use App\Http\Controllers\Api\CampaignLeadController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\SocialPostController;
use Illuminate\Support\Facades\Route;

/*
 * The public API, at `/api/v1`. A token belongs to one project, so no URL
 * here names a project: `project.token` sets it from the token itself. Ids
 * are looked up inside the actions, never route-model-bound, for the same
 * reason as in `routes/app.php`: binding would run before the project scope
 * is set and resolve another project's rows.
 */
Route::prefix('v1')
    ->name('api.')
    ->middleware(['auth:sanctum', 'project.token', 'throttle:api'])
    ->whereNumber(['company', 'contact', 'campaign'])
    ->group(function (): void {
        Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');

        Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
        Route::get('contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');

        Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
        Route::get('campaigns/{campaign}/leads', [CampaignLeadController::class, 'index'])->name('campaigns.leads.index');
        Route::post('campaigns/{campaign}/enrolments', [CampaignEnrolmentController::class, 'store'])->name('campaigns.enrolments.store');

        Route::post('social-posts', [SocialPostController::class, 'store'])->name('social-posts.store');
    });
