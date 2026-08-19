<?php

use App\Http\Controllers\Account\AccountDeletionController;
use App\Http\Controllers\Account\TwoFactorController;
use App\Http\Controllers\AppSettings\AgentController;
use App\Http\Controllers\AppSettings\KnownHostController;
use App\Http\Controllers\AppSettings\LimitController;
use App\Http\Controllers\AppSettings\ProviderController;
use App\Http\Controllers\AppSettings\ProviderTestController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignGenerationController;
use App\Http\Controllers\CampaignStepController;
use App\Http\Controllers\CampaignStepOrderController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyStatusController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactSearchController;
use App\Http\Controllers\ContactStatusController;
use App\Http\Controllers\ConversationReplyController;
use App\Http\Controllers\CurrentProjectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscoveryRunCancellationController;
use App\Http\Controllers\DiscoveryRunController;
use App\Http\Controllers\DiscoveryTaskReplayController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LeadImportController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\MailboxTestController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectKnowledgeBaseController;
use App\Http\Controllers\TargetProfileController;
use App\Http\Controllers\TargetProfileDerivationController;
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
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        /*
         * The current project comes from the session, so none of these carry it
         * in the URL: switching projects leaves you on the page you were on.
         */
        Route::prefix('settings')->name('settings.')->group(function (): void {
            Route::redirect('/', '/app/settings/project');

            Route::get('project', [ProjectController::class, 'edit'])->name('project.edit');
            Route::put('project', [ProjectController::class, 'update'])->name('project.update');
            Route::delete('project', [ProjectController::class, 'destroy'])->name('project.destroy');

            /*
             * Mailboxes belong to the ORGANIZATION, so these sit outside
             * anything project-scoped: one address is often used by two
             * products and never by a third, and which projects may send
             * through it is a grant on the pivot.
             */
            Route::get('mailboxes', [MailboxController::class, 'index'])->name('mailboxes.index');
            Route::post('mailboxes', [MailboxController::class, 'store'])->name('mailboxes.store');
            Route::put('mailboxes/{mailbox}', [MailboxController::class, 'update'])->name('mailboxes.update');
            Route::delete('mailboxes/{mailbox}', [MailboxController::class, 'destroy'])->name('mailboxes.destroy');
            Route::post('mailboxes/{mailbox}/test', [MailboxTestController::class, 'store'])
                ->name('mailboxes.test');

            Route::get('knowledge-base', [ProjectKnowledgeBaseController::class, 'edit'])
                ->name('knowledge-base.edit');
            Route::put('knowledge-base', [ProjectKnowledgeBaseController::class, 'update'])
                ->name('knowledge-base.update');
        });

        /*
         * Not under settings: who the search goes after is read and corrected
         * before every run, and the runs themselves land beside it. Settings is
         * for what you set once.
         */
        /*
         * Targets. The profiles ARE the navigation of this section — each one
         * has its own page and its own searches — so every route under it
         * shares the list and the state of a running derivation.
         */
        Route::middleware('targets.share')->group(function (): void {
            Route::post('targets/derive', [TargetProfileDerivationController::class, 'store'])
                ->name('targets.derive');
            Route::get('targets/{target}/searches', [DiscoveryRunController::class, 'index'])
                ->name('targets.searches');
            Route::resource('targets', TargetProfileController::class)
                ->only(['index', 'create', 'store', 'show', 'update', 'destroy']);

            /*
             * One flag stops a run and one dispatch replays a single node,
             * which is why neither needs more than a POST.
             */
            Route::post('discovery-runs/{discovery_run}/cancel', [DiscoveryRunCancellationController::class, 'store'])
                ->name('discovery-runs.cancel');
            Route::post('discovery-tasks/{discovery_task}/replay', [DiscoveryTaskReplayController::class, 'store'])
                ->name('discovery-tasks.replay');
            Route::resource('discovery-runs', DiscoveryRunController::class)
                ->only(['show', 'store']);
        });

        /*
         * What those searches came back with. Saying where a company stands
         * keeps the row: deleting it would only mean the next run finds the
         * company again, and four of the statuses exist precisely so it is
         * never written to.
         */
        Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::put('companies/{company}/status', [CompanyStatusController::class, 'update'])
            ->name('companies.status');

        /*
         * And the people at them. One search covers one company, or every kept
         * company nobody has looked at yet — clicking forty times is work the
         * app should be doing.
         */
        Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::post('contacts/search', [ContactSearchController::class, 'store'])->name('contacts.search');
        Route::put('contacts/{contact}/status', [ContactStatusController::class, 'update'])
            ->name('contacts.status');

        /*
         * What gets written to them. The agent writes the sequence from the
         * product and the segment; the editor is where it gets corrected, and
         * composing one by hand is the escape hatch rather than the front door.
         */
        Route::post('campaigns/generate', [CampaignGenerationController::class, 'store'])
            ->name('campaigns.generate');
        Route::put('campaigns/{campaign}/step-order', [CampaignStepOrderController::class, 'update'])
            ->name('campaigns.step-order');
        Route::resource('campaigns.steps', CampaignStepController::class)
            ->only(['store', 'update', 'destroy'])
            ->shallow(false);
        Route::resource('campaigns', CampaignController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        /*
         * Who answered. Only real conversations reach this screen: a lead that
         * was written to and said nothing is a sequence still running, not an
         * inbox entry. Answering by hand stops the sequence — somebody being
         * written to by a person must not also get the queued follow-up.
         */
        Route::get('inbox', [InboxController::class, 'index'])->name('inbox');
        Route::post('inbox/{conversation}/reply', [ConversationReplyController::class, 'store'])
            ->name('inbox.reply');

        /*
         * A list somebody already had. A button on Leads, never a section of
         * its own — importing is one way leads arrive, not a place you go.
         */
        Route::get('contacts/import/template', [LeadImportController::class, 'show'])
            ->name('contacts.template');
        Route::post('contacts/import', [LeadImportController::class, 'store'])->name('contacts.import');
    });

    /*
     * App settings: instance scope — one install, one operator, never granted through an
     * organization. Outside `project.require` on purpose — which model an agent
     * runs on has nothing to do with whichever project is selected.
     */
    Route::prefix('app-settings')->name('app-settings.')->middleware('can:manage-app-settings')->group(function (): void {
        Route::redirect('/', '/app/app-settings/provider');

        Route::get('provider', [ProviderController::class, 'edit'])->name('provider.edit');
        Route::put('provider', [ProviderController::class, 'update'])->name('provider.update');
        Route::delete('provider/{provider}', [ProviderController::class, 'destroy'])->name('provider.destroy');
        Route::post('provider/{provider}/test', [ProviderTestController::class, 'store'])->name('provider.test');

        Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
        Route::put('agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
        Route::delete('agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');

        Route::get('limits', [LimitController::class, 'edit'])->name('limits.edit');
        Route::put('limits', [LimitController::class, 'update'])->name('limits.update');

        Route::get('hosts', [KnownHostController::class, 'index'])->name('hosts.index');
        Route::put('hosts/{known_host}', [KnownHostController::class, 'update'])->name('hosts.update');
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
