<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CampaignEnrolmentController;
use App\Http\Controllers\Api\CampaignLeadController;
use App\Http\Controllers\Api\CompanyBulkDeleteController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyNoteController;
use App\Http\Controllers\Api\ContactBulkDeleteController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactNoteController;
use App\Http\Controllers\Api\DiscoveryRunController;
use App\Http\Controllers\Api\IdeaController;
use App\Http\Controllers\Api\KnowledgeBaseController;
use App\Http\Controllers\Api\KnownClientController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\RedditReplyController;
use App\Http\Controllers\Api\SocialPostController;
use App\Http\Controllers\Api\TargetProfileController;
use Illuminate\Support\Facades\Route;

/*
 * The public API, at `/api/v1`. A token belongs to an organization and
 * reaches its projects (`token.scope`); every other URL names one of them,
 * and `token.project` sets it, 404 when it is not the token's. Ids are
 * looked up inside the actions, never route-model-bound, for the same reason
 * as in `routes/app.php`: binding would run before the project scope is set
 * and resolve another project's rows.
 */
Route::prefix('v1')
    ->name('api.')
    ->middleware(['auth:sanctum', 'token.scope', 'throttle:api'])
    ->group(function (): void {
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');

        Route::prefix('projects/{project:slug}')
            ->middleware('token.project')
            ->whereNumber(['company', 'contact', 'campaign', 'note', 'target_profile', 'discovery_run', 'social_post', 'article', 'idea', 'reddit_reply'])
            ->group(function (): void {
                Route::get('knowledge-base', [KnowledgeBaseController::class, 'show'])->name('knowledge-base.show');
                Route::put('knowledge-base', [KnowledgeBaseController::class, 'update'])->name('knowledge-base.update');

                Route::get('recommendations', [RecommendationController::class, 'index'])->name('recommendations.index');
                Route::post('recommendations', [RecommendationController::class, 'store'])->name('recommendations.store');
                Route::patch('recommendations/{recommendation}', [RecommendationController::class, 'update'])->name('recommendations.update');
                Route::delete('recommendations/{recommendation}', [RecommendationController::class, 'destroy'])->name('recommendations.destroy');

                Route::apiResource('target-profiles', TargetProfileController::class);

                Route::get('discovery-runs', [DiscoveryRunController::class, 'index'])->name('discovery-runs.index');
                Route::post('discovery-runs', [DiscoveryRunController::class, 'store'])->name('discovery-runs.store');
                Route::get('discovery-runs/{discovery_run}', [DiscoveryRunController::class, 'show'])->name('discovery-runs.show');
                Route::post('discovery-runs/{discovery_run}/cancel', [DiscoveryRunController::class, 'cancel'])->name('discovery-runs.cancel');

                Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
                Route::delete('companies', [CompanyBulkDeleteController::class, 'destroy'])->name('companies.destroy-all');
                Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
                Route::get('companies/{company}/notes', [CompanyNoteController::class, 'index'])->name('companies.notes.index');
                Route::post('companies/{company}/notes', [CompanyNoteController::class, 'store'])->name('companies.notes.store');
                Route::patch('companies/{company}/notes/{note}', [CompanyNoteController::class, 'update'])->name('companies.notes.update');
                Route::delete('companies/{company}/notes/{note}', [CompanyNoteController::class, 'destroy'])->name('companies.notes.destroy');

                Route::post('known-clients', [KnownClientController::class, 'store'])->name('known-clients.store');

                Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
                Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
                Route::delete('contacts', [ContactBulkDeleteController::class, 'destroy'])->name('contacts.destroy-all');
                Route::get('contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
                Route::get('contacts/{contact}/notes', [ContactNoteController::class, 'index'])->name('contacts.notes.index');
                Route::post('contacts/{contact}/notes', [ContactNoteController::class, 'store'])->name('contacts.notes.store');
                Route::patch('contacts/{contact}/notes/{note}', [ContactNoteController::class, 'update'])->name('contacts.notes.update');
                Route::delete('contacts/{contact}/notes/{note}', [ContactNoteController::class, 'destroy'])->name('contacts.notes.destroy');

                Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
                Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
                Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
                Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
                Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
                Route::get('campaigns/{campaign}/leads', [CampaignLeadController::class, 'index'])->name('campaigns.leads.index');
                Route::post('campaigns/{campaign}/enrolments', [CampaignEnrolmentController::class, 'store'])->name('campaigns.enrolments.store');

                Route::get('social-posts', [SocialPostController::class, 'index'])->name('social-posts.index');
                Route::post('social-posts', [SocialPostController::class, 'store'])->name('social-posts.store');
                Route::get('social-posts/{social_post}', [SocialPostController::class, 'show'])->name('social-posts.show');
                Route::patch('social-posts/{social_post}', [SocialPostController::class, 'update'])->name('social-posts.update');
                Route::delete('social-posts/{social_post}', [SocialPostController::class, 'destroy'])->name('social-posts.destroy');

                Route::get('articles', [ArticleController::class, 'index'])->name('articles.index');
                Route::post('articles', [ArticleController::class, 'store'])->name('articles.store');
                Route::get('articles/{article}', [ArticleController::class, 'show'])->name('articles.show');
                Route::patch('articles/{article}', [ArticleController::class, 'update'])->name('articles.update');
                Route::delete('articles/{article}', [ArticleController::class, 'destroy'])->name('articles.destroy');

                Route::get('article-ideas', [IdeaController::class, 'index'])->name('article-ideas.index');
                Route::delete('article-ideas/{idea}', [IdeaController::class, 'destroy'])->name('article-ideas.destroy');

                Route::get('reddit-replies', [RedditReplyController::class, 'index'])->name('reddit-replies.index');
                Route::get('reddit-replies/{reddit_reply}', [RedditReplyController::class, 'show'])->name('reddit-replies.show');
                Route::patch('reddit-replies/{reddit_reply}', [RedditReplyController::class, 'update'])->name('reddit-replies.update');
                Route::delete('reddit-replies/{reddit_reply}', [RedditReplyController::class, 'destroy'])->name('reddit-replies.destroy');
            });
    });
