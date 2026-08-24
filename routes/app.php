<?php

use App\Http\Controllers\Account\AccountDeletionController;
use App\Http\Controllers\Account\TwoFactorController;
use App\Http\Controllers\AppSettings\AgentController;
use App\Http\Controllers\AppSettings\KnownHostController;
use App\Http\Controllers\AppSettings\LimitController;
use App\Http\Controllers\AppSettings\ProviderController;
use App\Http\Controllers\AppSettings\ProviderTestController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignEnrolmentController;
use App\Http\Controllers\CampaignGenerationController;
use App\Http\Controllers\CampaignStatusController;
use App\Http\Controllers\CampaignStepController;
use App\Http\Controllers\CampaignStepOrderController;
use App\Http\Controllers\CompanyApprovalController;
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
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OnboardingSearchController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectKnowledgeBaseController;
use App\Http\Controllers\Settings\MemberController;
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

/*
 * Reachable whether the visitor is authenticated or not: an existing user
 * just accepts, a brand new one sets a name and password first. Neither
 * branch is `guest`-only, which is why this is not in the group above.
 *
 * No `{token}`: the query string itself (organization, email, role,
 * signature) IS the invite, via `URL::temporarySignedRoute`. The GET route
 * is what the signature is generated against; the POST reuses the identical
 * query string and validates it the same way.
 */
Route::get('invitations/accept', [InvitationController::class, 'show'])->name('invitations.accept');
Route::post('invitations/accept', [InvitationController::class, 'store']);

Route::middleware(['auth', 'project.set'])->group(function (): void {
    /*
     * Switching projects and creating one are the two things reachable without
     * a project already selected. Everything else would have nothing to show.
     */
    Route::put('current-project/{project}', [CurrentProjectController::class, 'update'])
        ->middleware('can:view,project')
        ->name('current-project.update');

    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');

    Route::middleware('project.require')->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        /*
         * The first ten minutes. Somebody who has just given the address of
         * their product watches it being read, agrees with what was understood,
         * and sees the search start: rather than landing on a dashboard of
         * zeroes with four screens to find in the right order.
         */
        Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding');
        Route::post('onboarding/searches', [OnboardingSearchController::class, 'store'])
            ->name('onboarding.searches');

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

            /*
             * Organization-scoped, same reasoning as mailboxes above: who is
             * on the team has nothing to do with which project is currently
             * selected, only with which organization owns it.
             */
            Route::get('members', [MemberController::class, 'index'])->name('members.index');
            Route::post('members', [MemberController::class, 'store'])->name('members.store');
            Route::put('members/{user}', [MemberController::class, 'update'])->name('members.update');
            Route::delete('members/{user}', [MemberController::class, 'destroy'])->name('members.destroy');

            Route::get('knowledge-base', [ProjectKnowledgeBaseController::class, 'edit'])
                ->name('knowledge-base.edit');
            Route::put('knowledge-base', [ProjectKnowledgeBaseController::class, 'update'])
                ->name('knowledge-base.update');
            /*
             * Answered from onboarding as well as from here, which is why it
             * redirects back rather than to the settings screen.
             */
            Route::put('knowledge-base/answers', [ProjectKnowledgeBaseController::class, 'answer'])
                ->name('knowledge-base.answers');
        });

        /*
         * Not under settings: who the search goes after is read and corrected
         * before every run, and the runs themselves land beside it. Settings is
         * for what you set once.
         */
        /*
         * Targets. The profiles ARE the navigation of this section: each one
         * has its own page and its own searches, so every route under it
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
        Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');

        /*
         * And the people at them. One search covers one company, or every kept
         * company nobody has looked at yet: clicking forty times is work the
         * app should be doing.
         */
        Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::post('contacts/search', [ContactSearchController::class, 'store'])->name('contacts.search');
        Route::put('contacts/{contact}/status', [ContactStatusController::class, 'update'])
            ->name('contacts.status');

        /*
         * One person's whole history. Where the address came from, which
         * sequences they are in, every mail either way. Registered after the
         * literal segments above so `contacts/import` is never read as an id.
         */
        Route::get('contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');

        /*
         * What gets written to them. The agent writes the sequence from the
         * product and the segment; the editor is where it gets corrected, and
         * composing one by hand is the escape hatch rather than the front door.
         */
        Route::post('campaigns/generate', [CampaignGenerationController::class, 'store'])
            ->name('campaigns.generate');
        /*
         * The segments with nothing written for them. What is missing never
         * appears on a list of what exists, so it takes a button of its own.
         */
        Route::post('campaigns/generate/missing', [CampaignGenerationController::class, 'missing'])
            ->name('campaigns.generate.missing');
        Route::put('campaigns/{campaign}/step-order', [CampaignStepOrderController::class, 'update'])
            ->name('campaigns.step-order');
        /*
         * The one switch that makes mail leave, thrown from the list as much as
         * from the campaign, so it is not part of the campaign's own form.
         */
        Route::put('campaigns/{campaign}/status', [CampaignStatusController::class, 'update'])
            ->name('campaigns.status');
        /*
         * The same enrolment the switch performs, on demand: people approved
         * after a campaign started are otherwise waiting on a scheduled tick
         * that a supervised project never gets.
         */
        Route::post('campaigns/{campaign}/enrol', [CampaignEnrolmentController::class, 'store'])
            ->name('campaigns.enrol');
        Route::resource('campaigns.steps', CampaignStepController::class)
            ->only(['store', 'update', 'destroy'])
            ->shallow(false);
        /*
         * The second page of one campaign, the way a target profile has its
         * searches: the mails and the run are read at different moments, and
         * one screen carrying both means scrolling past the run to edit a mail.
         */
        Route::get('campaigns/{campaign}/delivery', [CampaignController::class, 'delivery'])
            ->name('campaigns.delivery');
        Route::resource('campaigns', CampaignController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        /*
         * Who answered. Only real conversations reach this screen: a lead that
         * was written to and said nothing is a sequence still running, not an
         * inbox entry. Answering by hand stops the sequence: somebody being
         * written to by a person must not also get the queued follow-up.
         */
        Route::get('inbox', [InboxController::class, 'index'])->name('inbox');
        Route::post('inbox/{conversation}/reply', [ConversationReplyController::class, 'store'])
            ->name('inbox.reply');

        /*
         * A list somebody already had. A button on Leads, never a section of
         * its own: importing is one way leads arrive, not a place you go.
         */
        Route::get('contacts/import/template', [LeadImportController::class, 'show'])
            ->name('contacts.template');
        Route::post('contacts/import', [LeadImportController::class, 'store'])->name('contacts.import');

        /*
         * The go-ahead on a company, which is what lets the people found there
         * enter a sequence. Taken in batches because that is how the list is
         * worked through.
         */
        Route::put('companies/approval', [CompanyApprovalController::class, 'update'])
            ->name('companies.approval');
    });

    /*
     * App settings: instance scope. One install, one operator, never granted through an
     * organization. Outside `project.require` on purpose: which model an agent
     * runs on has nothing to do with whichever project is selected.
     */
    Route::prefix('app-settings')->name('app-settings.')->middleware('can:manage-app-settings')->group(function (): void {
        Route::redirect('/', '/app/app-settings/provider');

        Route::get('provider', [ProviderController::class, 'edit'])->name('provider.edit');
        Route::put('provider', [ProviderController::class, 'update'])->name('provider.update');
        Route::delete('provider/{provider}', [ProviderController::class, 'destroy'])->name('provider.destroy');
        Route::post('provider/{provider}/test', [ProviderTestController::class, 'store'])->name('provider.test');

        Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
        /*
         * Before `agents/{agent}`, or the word `provider` would be read as an
         * agent slug and 404.
         */
        Route::put('agents/provider', [AgentController::class, 'switchProvider'])
            ->name('agents.provider');
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
