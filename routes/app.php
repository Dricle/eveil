<?php

use App\Cloud\Http\Controllers\AutoTopUpController;
use App\Cloud\Http\Controllers\BillingPortalController;
use App\Cloud\Http\Controllers\CheckoutController;
use App\Cloud\Http\Controllers\OrganizationBillingController;
use App\Cloud\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\Account\AccountDeletionController;
use App\Http\Controllers\Account\TwoFactorController;
use App\Http\Controllers\AiInstructionsController;
use App\Http\Controllers\AppSettings\AgentController;
use App\Http\Controllers\AppSettings\BillingController;
use App\Http\Controllers\AppSettings\CreditPriceController;
use App\Http\Controllers\AppSettings\EmailExampleController;
use App\Http\Controllers\AppSettings\EmailExampleThresholdController;
use App\Http\Controllers\AppSettings\KnownHostController;
use App\Http\Controllers\AppSettings\LimitController;
use App\Http\Controllers\AppSettings\LinkedinCredentialsController;
use App\Http\Controllers\AppSettings\LinkedinExampleThresholdController;
use App\Http\Controllers\AppSettings\LinkedinPostExampleController;
use App\Http\Controllers\AppSettings\LinkedinStatsCredentialsController;
use App\Http\Controllers\AppSettings\ProviderController;
use App\Http\Controllers\AppSettings\ProviderTestController;
use App\Http\Controllers\AppSettings\SendingController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignEnrolmentController;
use App\Http\Controllers\CampaignGenerationController;
use App\Http\Controllers\CampaignStatusController;
use App\Http\Controllers\CampaignStepController;
use App\Http\Controllers\CampaignStepOrderController;
use App\Http\Controllers\CodeRepositoryController;
use App\Http\Controllers\CompanyApprovalController;
use App\Http\Controllers\CompanyBulkStatusController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyNoteController;
use App\Http\Controllers\CompanyStatusController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactSearchController;
use App\Http\Controllers\ContactStatusController;
use App\Http\Controllers\ConversationAttentionController;
use App\Http\Controllers\ConversationReplyController;
use App\Http\Controllers\CurrentProjectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscoveryLinkController;
use App\Http\Controllers\DiscoveryRunCancellationController;
use App\Http\Controllers\DiscoveryRunController;
use App\Http\Controllers\DiscoveryTaskReplayController;
use App\Http\Controllers\EmailInstructionsController;
use App\Http\Controllers\EvieChatController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\KnownClientController;
use App\Http\Controllers\LeadImportController;
use App\Http\Controllers\LeadNoteController;
use App\Http\Controllers\LinkedinAccountController;
use App\Http\Controllers\LinkedinCadenceController;
use App\Http\Controllers\LinkedinInstructionsController;
use App\Http\Controllers\LinkedinOAuthController;
use App\Http\Controllers\LinkedinPostController;
use App\Http\Controllers\LinkedinStatsOAuthController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\MailboxReactivateController;
use App\Http\Controllers\MailboxTestController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OnboardingSearchController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectKnowledgeBaseController;
use App\Http\Controllers\Settings\MemberController;
use App\Http\Controllers\StepVariantController;
use App\Http\Controllers\StepVariantGenerationController;
use App\Http\Controllers\TargetProfileActivationController;
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

Route::middleware(['auth', 'verified', 'project.set'])->group(function (): void {
    /*
     * Switching projects and creating one are the two things reachable without
     * a project already selected. Everything else would have nothing to show.
     */
    Route::put('current-project/{project}', [CurrentProjectController::class, 'update'])
        ->middleware('can:view,project')
        ->name('current-project.update');

    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');

    Route::get('organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');

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
         * The persistent chat panel: app-wide, not tied to any one page's
         * props, so it is a plain JSON/SSE endpoint rather than an Inertia
         * render. GET restores the transcript on mount/project switch, POST
         * streams one turn (or resumes a paused one with approval decisions),
         * DELETE is what "clear" sends to start a fresh conversation.
         */
        Route::get('chat', [EvieChatController::class, 'index'])->name('chat.show');
        Route::post('chat', [EvieChatController::class, 'store'])->name('chat.store');
        Route::delete('chat', [EvieChatController::class, 'destroy'])->name('chat.destroy');

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
             * Both writing-tone boxes together - see `AiInstructionsController`.
             * Each saves through its own small route/controller since a
             * different agent reads each one (`EveilAgent::
             * emailWritingInstructions()` / `linkedinInstructions()`).
             */
            Route::get('ai-instructions', [AiInstructionsController::class, 'edit'])
                ->name('ai-instructions.edit');
            Route::put('ai-instructions/emails', [EmailInstructionsController::class, 'update'])
                ->name('ai-instructions.emails.update');
            Route::put('ai-instructions/linkedin', [LinkedinInstructionsController::class, 'update'])
                ->name('ai-instructions.linkedin.update');

            /*
             * Organization-scoped, same reasoning as mailboxes below: a
             * rename has nothing to do with which project is currently
             * selected, only with which organization owns it.
             */
            Route::get('organization/general', [OrganizationController::class, 'edit'])
                ->name('organization.general.edit');
            Route::put('organization/general', [OrganizationController::class, 'update'])
                ->name('organization.general.update');

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
            Route::post('mailboxes/{mailbox}/reactivate', [MailboxReactivateController::class, 'store'])
                ->name('mailboxes.reactivate');

            /*
             * LinkedIn account belongs to the ORGANIZATION, same reasoning
             * as mailboxes above: one LinkedIn identity is often posted
             * through by several products and never a third. The posting
             * cadence lives on the posts queue instead (`linkedin.posts.
             * cadence` below): it is a decision about that queue's own
             * rhythm, not about the account itself. Literal segments before
             * {linkedinAccount}, or "connect" would themselves be read as
             * an account id.
             */
            Route::get('linkedin', [LinkedinAccountController::class, 'index'])->name('linkedin.index');
            Route::get('linkedin/connect', [LinkedinOAuthController::class, 'redirect'])
                ->name('linkedin.connect');
            Route::put('linkedin/{linkedinAccount}', [LinkedinAccountController::class, 'update'])
                ->name('linkedin.update');
            Route::delete('linkedin/{linkedinAccount}', [LinkedinAccountController::class, 'destroy'])
                ->name('linkedin.destroy');
            /*
             * The SECOND, optional OAuth connection - the Community
             * Management app, per account, only ever reachable once that
             * account already exists.
             */
            Route::get('linkedin/{linkedinAccount}/stats/connect', [LinkedinStatsOAuthController::class, 'redirect'])
                ->name('linkedin.stats.connect');

            /*
             * Cloud billing. The route exists in both editions (one repo,
             * nothing withheld) but is reachable only through a nav link
             * cloud renders: self-hosted has no Stripe key, so a direct hit
             * here fails at the Stripe call rather than doing anything.
             */
            Route::get('organization/billing', [OrganizationBillingController::class, 'edit'])
                ->name('organization.billing.edit');
            Route::post('organization/billing/checkout', [CheckoutController::class, 'store'])
                ->name('organization.billing.checkout');
            Route::put('organization/billing/auto-topup', [AutoTopUpController::class, 'update'])
                ->name('organization.billing.auto-topup');
            Route::get('organization/billing/portal', [BillingPortalController::class, 'create'])
                ->name('organization.billing.portal');

            /*
             * Off-session charging needs a card on file before the wallet
             * ever crosses the threshold. Stripe-hosted (Checkout in `setup`
             * mode, no line items): the redirect IS the whole flow, nothing
             * to render on our side.
             */
            Route::get('organization/billing/payment-method', [PaymentMethodController::class, 'create'])
                ->name('organization.billing.payment-method.create');

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

            /*
             * Embedded in the Knowledge Base screen, not a page of its own.
             * `store` and `retry` both start the deep, tool-calling read
             * (`App\Jobs\ExploreRepo`, priced at `CreditPrice::current
             * ('repo-explorer')`): the frontend confirms the cost before
             * either request is sent.
             */
            Route::post('repositories', [CodeRepositoryController::class, 'store'])
                ->name('repositories.store');
            Route::delete('repositories/{codeRepository}', [CodeRepositoryController::class, 'destroy'])
                ->name('repositories.destroy');
            Route::post('repositories/{codeRepository}/retry', [CodeRepositoryController::class, 'retry'])
                ->name('repositories.retry');
        });

        /*
         * The approval queue every post source lands in: knowledge base,
         * client win, news, or drafted by talking to Evie. Its own nav
         * item rather than a Settings page: new drafts appear on their own
         * schedule, same reasoning as Targets living outside Settings.
         * The connected account itself lives in Settings -> the account
         * is organization-scoped config, set once, not reread on a
         * schedule the way this queue is.
         */
        Route::prefix('linkedin')->name('linkedin.')->group(function (): void {
            Route::get('posts', [LinkedinPostController::class, 'index'])->name('posts.index');
            /*
             * Literal segment before {linkedin_post} below, or "cadence"
             * would itself be read as a post id.
             */
            Route::put('posts/cadence', [LinkedinCadenceController::class, 'update'])
                ->name('posts.cadence');
            Route::put('posts/{linkedin_post}', [LinkedinPostController::class, 'update'])
                ->name('posts.update');
            Route::post('posts/{linkedin_post}/approve', [LinkedinPostController::class, 'approve'])
                ->name('posts.approve');
            /*
             * Reject keeps the row (with an optional reason, fed back into
             * the writer's prompt); destroy below is a hard delete. Two
             * different actions on purpose - see `LinkedinPostController`.
             */
            Route::post('posts/{linkedin_post}/reject', [LinkedinPostController::class, 'reject'])
                ->name('posts.reject');
            Route::delete('posts/{linkedin_post}', [LinkedinPostController::class, 'destroy'])
                ->name('posts.destroy');
            /*
             * Project-scoped only: stamps `promoted_at`, never writes to the
             * shared instance-wide pool - see `LinkedinPostController::promote()`.
             */
            Route::post('posts/{linkedin_post}/promote', [LinkedinPostController::class, 'promote'])
                ->name('posts.promote');

            /*
             * Reached from LinkedIn itself, not from a link in the app:
             * the redirect_uri given at authorize time. Not a settings
             * page a user navigates to, so it stays outside that prefix.
             */
            Route::get('oauth/callback', [LinkedinOAuthController::class, 'callback'])
                ->name('oauth.callback');
            Route::get('stats/oauth/callback', [LinkedinStatsOAuthController::class, 'callback'])
                ->name('stats.oauth.callback');
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
            Route::post('targets/{target}/activation', [TargetProfileActivationController::class, 'store'])
                ->name('targets.activation');

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
         * The account-level timeline: free text typed by hand, never sent
         * anywhere. Same reasoning as `contacts/{contact}/notes` below.
         */
        Route::post('companies/{company}/notes', [CompanyNoteController::class, 'store'])
            ->name('companies.notes.store');
        Route::delete('companies/{company}/notes/{note}', [CompanyNoteController::class, 'destroy'])
            ->name('companies.notes.destroy');

        Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');

        /*
         * A lead somebody already had. A button on Companies, never a section
         * of its own: pasting links is one way companies arrive, not a place
         * you go. Same reasoning as `contacts/import` below.
         */
        Route::post('companies/links', [DiscoveryLinkController::class, 'store'])
            ->name('companies.links.store');

        /*
         * A client the user already had before any search ran. Emails or
         * websites, marked `client` on arrival so a later discovery run never
         * writes the row `new` in the first place.
         */
        Route::post('companies/known-clients', [KnownClientController::class, 'store'])
            ->name('companies.known-clients.store');

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
         * The timeline: free text typed by hand, never sent anywhere. The
         * contact sheet and the inbox's side panel both write here, so
         * either one adding a note is instantly the other's history too.
         */
        Route::post('contacts/{contact}/notes', [LeadNoteController::class, 'store'])
            ->name('contacts.notes.store');
        Route::delete('contacts/{contact}/notes/{note}', [LeadNoteController::class, 'destroy'])
            ->name('contacts.notes.destroy');

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
         * A step's mail is one variant among possibly several: this is the
         * A/B test itself, a second wording added under the same step.
         */
        Route::resource('campaigns.steps.variants', StepVariantController::class)
            ->only(['store', 'update', 'destroy'])
            ->shallow(false);
        /*
         * The agent writes the second wording; the CRUD route above is the
         * escape hatch for editing or replacing it by hand afterwards.
         */
        Route::post('campaigns/{campaign}/steps/{step}/variants/generate', [StepVariantGenerationController::class, 'store'])
            ->name('campaigns.steps.variants.generate');
        /*
         * Rewriting one existing version per an instruction, rather than
         * adding another one to A/B test against it.
         */
        Route::post('campaigns/{campaign}/steps/{step}/variants/{variant}/regenerate', [StepVariantGenerationController::class, 'regenerate'])
            ->name('campaigns.steps.variants.regenerate');
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
         * Who answered, filed one folder per status plus `sent` - see
         * `InboxController`. Only real conversations reach any folder but
         * `sent`: a lead that was written to and said nothing is a sequence
         * still running, not an inbox entry. Answering by hand stops the
         * sequence: somebody being written to by a person must not also get
         * the queued follow-up.
         *
         * `{folder?}` is a route segment rather than a query param on
         * purpose: a param a pagination link can silently drop switches the
         * screen back to the default folder mid-click, which is exactly the
         * bug a segment makes impossible.
         */
        Route::get('inbox/{folder?}', [InboxController::class, 'index'])->name('inbox');
        Route::post('inbox/{conversation}/reply', [ConversationReplyController::class, 'store'])
            ->name('inbox.reply');
        Route::put('inbox/{conversation}/attention', [ConversationAttentionController::class, 'update'])
            ->name('inbox.attention');

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

        /*
         * Where several companies stand, taken in one go from the bulk
         * toolbar - same reasoning as `companies/approval` above.
         */
        Route::put('companies/status', [CompanyBulkStatusController::class, 'update'])
            ->name('companies.status.bulk');
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

        Route::get('linkedin', [LinkedinCredentialsController::class, 'edit'])->name('linkedin.edit');
        Route::put('linkedin', [LinkedinCredentialsController::class, 'update'])->name('linkedin.update');
        Route::delete('linkedin', [LinkedinCredentialsController::class, 'destroy'])->name('linkedin.destroy');
        Route::put('linkedin/stats', [LinkedinStatsCredentialsController::class, 'update'])
            ->name('linkedin.stats.update');
        Route::delete('linkedin/stats', [LinkedinStatsCredentialsController::class, 'destroy'])
            ->name('linkedin.stats.destroy');

        Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
        /*
         * Before `agents/{agent}`, or the word `provider` would be read as an
         * agent slug and 404.
         */
        Route::put('agents/provider', [AgentController::class, 'switchProvider'])
            ->name('agents.provider');
        Route::put('agents', [AgentController::class, 'updateMany'])->name('agents.update-many');
        Route::put('agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
        Route::delete('agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');
        Route::post('agents/{agent}/credit-price', [CreditPriceController::class, 'store'])->name('agents.credit-price');

        Route::get('limits', [LimitController::class, 'edit'])->name('limits.edit');
        Route::put('limits', [LimitController::class, 'update'])->name('limits.update');

        Route::get('sending', [SendingController::class, 'edit'])->name('sending.edit');
        Route::put('sending', [SendingController::class, 'update'])->name('sending.update');

        Route::get('hosts', [KnownHostController::class, 'index'])->name('hosts.index');
        Route::put('hosts/{known_host}', [KnownHostController::class, 'update'])->name('hosts.update');

        // Cloud only in practice (`billing.*` is never read on self-hosted),
        // but not edition-gated at the route: `.ai/rules/controllers.md`'s
        // "404 for access, not a feature that doesn't apply here" - the nav
        // tab hides itself off `edition` instead.
        Route::get('billing', [BillingController::class, 'edit'])->name('billing.edit');
        Route::put('billing', [BillingController::class, 'update'])->name('billing.update');

        Route::get('email-examples', [EmailExampleController::class, 'index'])->name('email-examples.index');
        Route::post('email-examples', [EmailExampleController::class, 'store'])->name('email-examples.store');
        Route::delete('email-examples/{emailExample}', [EmailExampleController::class, 'destroy'])
            ->name('email-examples.destroy');
        Route::put('email-examples/thresholds', [EmailExampleThresholdController::class, 'update'])
            ->name('email-examples.thresholds');

        Route::get('linkedin-post-examples', [LinkedinPostExampleController::class, 'index'])
            ->name('linkedin-post-examples.index');
        Route::post('linkedin-post-examples', [LinkedinPostExampleController::class, 'store'])
            ->name('linkedin-post-examples.store');
        Route::delete('linkedin-post-examples/{linkedinPostExample}', [LinkedinPostExampleController::class, 'destroy'])
            ->name('linkedin-post-examples.destroy');
        Route::put('linkedin-post-examples/threshold', [LinkedinExampleThresholdController::class, 'update'])
            ->name('linkedin-post-examples.threshold');
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
