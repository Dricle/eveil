<?php

use App\Actions\PersonalizeMessage;
use App\Actions\WriteMissingCampaigns;
use App\Actions\WriteSequence;
use App\Ai\Agents\CompanyQualifier;
use App\Ai\Agents\ContactExtractor;
use App\Ai\Agents\MessagePersonalizer;
use App\Ai\Agents\SequenceWriter;
use App\Ai\Agents\TargetProfileDeriver;
use App\Ai\Agents\WebsiteAnalyst;
use App\Enums\AgentRunStatus;
use App\Enums\AutonomyLevel;
use App\Enums\CampaignStatus;
use App\Enums\CampaignStepType;
use App\Enums\EmailStatus;
use App\Enums\OutreachStatus;
use App\Enums\TargetProfileType;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\WriteCampaign;
use App\Models\AgentRun;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\CompanyTargetEvaluation;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Project;
use App\Models\StepVariant;
use App\Models\TargetProfile;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

/**
 * @return array{0: User, 1: Project}
 */
function sequencer(): array
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);

    return [$user, Project::factory()->for($user->organizations()->sole())->create([
        'knowledge_base' => ['what_it_does' => 'Commission-free ordering for restaurants.'],
    ])];
}

/**
 * @return array<string, mixed>
 */
function writtenSequence(): array
{
    return [
        'name' => 'Friteries wallonnes. Premier contact',
        'steps' => [
            [
                'type' => 'email',
                'delay_hours' => 0,
                'subject' => 'vos commandes en ligne',
                'body' => "Bonjour,\n\nVotre carte est sur Facebook…\n\nSi ce n'est pas pertinent, répondez STOP.",
                'intent' => 'Open on what their ordering looks like today.',
            ],
            ['type' => 'wait', 'delay_hours' => 72, 'subject' => '', 'body' => '', 'intent' => 'Let it breathe.'],
            [
                'type' => 'email',
                'delay_hours' => 0,
                'subject' => 'petite relance',
                'body' => 'Je reviens vers vous une dernière fois.',
                'intent' => 'One follow-up, then stop.',
            ],
        ],
    ];
}

function campaignFor(Project $project, ?TargetProfile $profile = null): Campaign
{
    $campaign = Campaign::factory()->create([
        'project_id' => $project->id,
        'target_profile_id' => $profile?->id,
        'status' => CampaignStatus::Draft,
    ]);

    $step = $campaign->steps()->create([
        'position' => 1,
        'type' => CampaignStepType::Email,
        'delay_hours' => null,
        'config' => ['intent' => 'Open on their ordering.'],
    ]);

    $step->variants()->create(['subject' => 'vos commandes', 'body' => 'Bonjour…', 'weight' => 1]);

    return $campaign->load('steps.variants');
}

it('writes a whole sequence from the product and the segment', function () {
    [, $project] = sequencer();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id, 'name' => 'Friteries wallonnes']);

    SequenceWriter::fake([writtenSequence()]);

    app(WriteSequence::class)->handle($project, $profile);

    $campaign = Campaign::sole();

    expect($campaign->name)->toBe('Friteries wallonnes. Premier contact')
        // A draft: writing a sequence never starts sending one.
        ->and($campaign->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->target_profile_id)->toBe($profile->id)
        ->and($campaign->steps()->pluck('type')->all())
        ->toBe([CampaignStepType::Email, CampaignStepType::Wait, CampaignStepType::Email])
        ->and($campaign->steps()->pluck('position')->all())->toBe([1, 2, 3]);

    $first = $campaign->steps()->first();

    expect($first->variants()->sole()->subject)->toBe('vos commandes en ligne')
        // Null language, not the market's: the body is rewritten per company in
        // the company's own language, and a value marks a hand-written one.
        ->and($first->variants()->sole()->language)->toBeNull()
        ->and($first->config['intent'])->toBe('Open on what their ordering looks like today.');
});

it('gives a wait step a duration even when the writer forgets one', function () {
    // A wait of zero runs the sequence straight through, which reads as
    // automation at the other end.
    [, $project] = sequencer();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);

    SequenceWriter::fake([[
        'name' => 'Sequence',
        'steps' => [['type' => 'wait', 'delay_hours' => 0, 'subject' => '', 'body' => '', 'intent' => '']],
    ]]);

    app(WriteSequence::class)->handle($project, $profile);

    expect(Campaign::sole()->steps()->sole()->delay_hours)->toBe(1);
});

it('refuses to write a sequence for a product it has not read', function () {
    [, $project] = sequencer();
    $project->update(['knowledge_base' => null]);
    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);

    expect(fn () => app(WriteSequence::class)->handle($project, $profile))
        ->toThrow(RuntimeException::class);
});

it('queues the writing and opens the run row before the worker picks it up', function () {
    // Between the click and a worker starting, a screen must be able to say
    // something is on its way rather than look like the button did nothing.
    Queue::fake();

    [$user, $project] = sequencer();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->post(route('campaigns.generate'), ['target_profile' => $profile->id])
        ->assertRedirect();

    Queue::assertPushed(WriteCampaign::class);

    expect(AgentRun::sole())
        ->agent->toBe('sequence-writer')
        ->status->toBe(AgentRunStatus::Pending);
});

it('personalises a step from what the qualifier observed about the company', function () {
    [, $project] = sequencer();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id, 'type' => TargetProfileType::Customer]);

    $company = Company::factory()->create([
        'project_id' => $project->id,
        'name' => 'Friterie du Centre',
        'language' => 'fr',
    ]);

    CompanyTargetEvaluation::factory()->create([
        'company_id' => $company->id,
        'target_profile_id' => $profile->id,
        'fit_score' => 88,
        'fit_reason' => 'Carte publiée en PDF, commandes par téléphone uniquement.',
    ]);

    $lead = Lead::factory()->create([
        'project_id' => $project->id,
        'company_id' => $company->id,
        'first_name' => 'Marcel',
        'email' => 'marcel@friterie.test',
    ]);

    MessagePersonalizer::fake([['subject' => 'votre carte en PDF', 'body' => 'Bonjour Marcel, …']]);

    $campaign = campaignFor($project, $profile);
    $written = app(PersonalizeMessage::class)->handle($campaign->steps->first(), $lead);

    expect($written['subject'])->toBe('votre carte en PDF');

    // What the model was given: the reason this company was kept, in the words
    // the qualifier used, plus the language the mail must be written in.
    $prompt = AgentRun::query()->where('agent', 'message-personalizer')->sole()->input;

    expect(json_encode($prompt, JSON_UNESCAPED_UNICODE))
        ->toContain('Carte publiée en PDF')
        ->toContain('Friterie du Centre');
});

it('previews the sequence on real leads and never invents one', function () {
    [$user, $project] = sequencer();

    $company = Company::factory()->create(['project_id' => $project->id, 'name' => 'Friterie du Centre']);

    Lead::factory()->create([
        'project_id' => $project->id,
        'company_id' => $company->id,
        'email' => 'marcel@friterie.test',
        'email_status' => EmailStatus::Valid,
    ]);

    // Never sent to, so never previewed either.
    Lead::factory()->create([
        'project_id' => $project->id,
        'company_id' => $company->id,
        'email' => 'mort@friterie.test',
        'email_status' => EmailStatus::Invalid,
    ]);

    MessagePersonalizer::fake([['subject' => 'votre carte', 'body' => 'Bonjour, …']]);

    $campaign = campaignFor($project);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        // A partial reload, which is the only request that resolves an
        // optional prop. The version header has to be the one the middleware
        // computes or Inertia answers 409 and nothing runs.
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Data' => 'sample',
            'X-Inertia-Partial-Component' => 'campaigns/Show',
        ])
        ->get(route('campaigns.show', [
            'campaign' => $campaign->id,
            'preview_step' => $campaign->steps->first()->id,
        ]))
        // A partial reload answers with JSON rather than the Inertia view, so
        // this reads the payload rather than going through assertInertia.
        ->assertJsonCount(1, 'props.sample.messages')
        ->assertJsonPath('props.sample.messages.0.lead', 'marcel@friterie.test')
        ->assertJsonPath('props.sample.messages.0.subject', 'votre carte');
});

it('does not personalise anything on an ordinary visit', function () {
    // One model call per lead: a page refresh must never spend them.
    [$user, $project] = sequencer();
    $campaign = campaignFor($project);

    MessagePersonalizer::fake([['subject' => 'never', 'body' => 'never']]);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('campaigns.show', $campaign))
        ->assertOk();

    expect(AgentRun::query()->where('agent', 'message-personalizer')->count())->toBe(0);
});

it('lets the user compose and reorder the steps themselves', function () {
    [$user, $project] = sequencer();
    $campaign = campaignFor($project);

    $this->actingAs($user)->withSession(['current_project_id' => $project->id]);

    $this->post(route('campaigns.steps.store', $campaign), [
        'type' => 'wait',
        'delay_hours' => 72,
    ])->assertRedirect();

    $campaign->refresh()->load('steps');

    expect($campaign->steps->pluck('position')->all())->toBe([1, 2]);

    // Positions are unique per campaign, so a swap that renumbers row by row
    // collides with the index halfway through.
    $this->put(route('campaigns.step-order', $campaign), [
        'steps' => $campaign->steps->pluck('id')->reverse()->values()->all(),
    ])->assertRedirect();

    expect($campaign->steps()->orderBy('position')->pluck('type')->all())
        ->toBe([CampaignStepType::Wait, CampaignStepType::Email]);
});

it('updates the mail in place rather than stacking a second variant behind it', function () {
    [$user, $project] = sequencer();
    $campaign = campaignFor($project);
    $step = $campaign->steps->first();

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->put(route('campaigns.steps.update', [$campaign, $step]), [
            'type' => 'email',
            'subject' => 'corrigé',
            'body' => 'Réécrit à la main.',
            'intent' => 'Open on their ordering.',
        ])->assertRedirect();

    expect(StepVariant::query()->where('campaign_step_id', $step->id)->count())->toBe(1)
        ->and(StepVariant::query()->where('campaign_step_id', $step->id)->sole()->subject)->toBe('corrigé');
});

it('refuses a wait step with no duration and a mail with no body', function () {
    [$user, $project] = sequencer();
    $campaign = campaignFor($project);

    $this->actingAs($user)->withSession(['current_project_id' => $project->id]);

    $this->post(route('campaigns.steps.store', $campaign), ['type' => 'wait'])
        ->assertSessionHasErrors('delay_hours');

    $this->post(route('campaigns.steps.store', $campaign), ['type' => 'email', 'subject' => 'hello'])
        ->assertSessionHasErrors('body');
});

it('answers 404 for a campaign belonging to another project', function () {
    [$user, $project] = sequencer();
    [, $other] = sequencer();

    $foreign = campaignFor($other);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('campaigns.show', $foreign))
        ->assertNotFound();

    expect(Campaign::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('sends the props the pages actually read', function () {
    // A resource collection arrives as a plain array and a single resource
    // unwrapped. Only a PAGINATED resource carries a `data` envelope. Reading
    // `campaigns.data` instead compiles, renders, and dies in the browser with
    // "Cannot read properties of undefined", which no server-side test sees
    // unless it asserts the shape.
    [$user, $project] = sequencer();
    TargetProfile::factory()->create(['project_id' => $project->id, 'name' => 'Friteries wallonnes']);
    $campaign = campaignFor($project);

    $this->actingAs($user)->withSession(['current_project_id' => $project->id]);

    $this->get(route('campaigns.index'))
        ->assertInertia(fn ($page) => $page
            ->has('campaigns', 1)
            ->where('campaigns.0.name', $campaign->name)
            ->where('campaigns.0.steps_count', 1)
            ->has('profiles', 1)
            ->where('profiles.0.name', 'Friteries wallonnes'));

    $this->get(route('campaigns.show', $campaign))
        ->assertInertia(fn ($page) => $page
            ->where('campaign.name', $campaign->name)
            ->has('campaign.steps', 1)
            ->where('campaign.steps.0.subject', 'vos commandes'));
});

it('never previews a lead the user has taken out of outreach', function () {
    [$user, $project] = sequencer();

    // Already a client of this product: the one row a cold sequence must never
    // reach, and no fit score can know it.
    $client = Company::factory()->create([
        'project_id' => $project->id,
        'name' => 'Friterie du Centre',
        'status' => OutreachStatus::Client,
    ]);

    Lead::factory()->create([
        'project_id' => $project->id,
        'company_id' => $client->id,
        'email' => 'patron@client.test',
        'email_status' => EmailStatus::Valid,
    ]);

    // Same again one person at a time: the company is still in the running.
    $prospect = Company::factory()->create(['project_id' => $project->id, 'name' => 'Friterie Neuve']);

    Lead::factory()->create([
        'project_id' => $project->id,
        'company_id' => $prospect->id,
        'email' => 'parti@neuve.test',
        'email_status' => EmailStatus::Valid,
        'status' => OutreachStatus::Lost,
    ]);

    Lead::factory()->create([
        'project_id' => $project->id,
        'company_id' => $prospect->id,
        'email' => 'marcel@neuve.test',
        'email_status' => EmailStatus::Valid,
    ]);

    MessagePersonalizer::fake([['subject' => 'votre carte', 'body' => 'Bonjour, …']]);

    $campaign = campaignFor($project);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Data' => 'sample',
            'X-Inertia-Partial-Component' => 'campaigns/Show',
        ])
        ->get(route('campaigns.show', [
            'campaign' => $campaign->id,
            'preview_step' => $campaign->steps->first()->id,
        ]))
        ->assertJsonCount(1, 'props.sample.messages')
        ->assertJsonPath('props.sample.messages.0.lead', 'marcel@neuve.test');
});

it('starts every project with the house style already in the box', function () {
    [, $project] = sequencer();

    // Dash punctuation is one of the cheapest tells that a machine wrote a
    // sentence, and everything sent from here is supposed to read as though a
    // person typed it. It sits in the box the user can see and edit.
    expect($project->prompt_instructions)->toBe(Project::DEFAULT_INSTRUCTIONS)
        ->and((string) (new SequenceWriter($project))->instructions())->toContain('Never use dash punctuation')
        // And in everything else whose output is read as prose: the portrait,
        // the segment rationales, and the fit reason that becomes the opening
        // line of the first mail.
        ->and((string) (new WebsiteAnalyst($project))->instructions())->toContain('Never use dash punctuation')
        ->and((string) (new TargetProfileDeriver($project))->instructions())->toContain('Never use dash punctuation')
        ->and((string) (new CompanyQualifier($project))->instructions())->toContain('Never use dash punctuation')
        // Not in the ones that only return fields: nobody reads those as prose,
        // and a strict-structure agent breaks rather than blurs as the prompt
        // grows.
        ->and((string) (new ContactExtractor($project))->instructions())->not->toContain('Never use dash punctuation');
});

it("appends the project's own writing instructions to the agents that write", function () {
    [, $project] = sequencer();

    expect((string) (new SequenceWriter($project))->instructions())
        ->not->toContain('Never use emoji')
        ->and((string) (new MessagePersonalizer($project))->instructions())
        ->not->toContain('Never use emoji');

    // Replacing the default rather than adding to it: the box is the user's.
    $project->update(['prompt_instructions' => 'Write in French. Never use emoji.']);

    expect((string) (new SequenceWriter($project))->instructions())
        ->toContain('Never use emoji')
        ->and((string) (new MessagePersonalizer($project))->instructions())
        ->toContain('Never use emoji')
        // Last and stated as overriding: it is a box the user filled in
        // themselves, and they expect it to win.
        ->and((string) (new MessagePersonalizer($project))->instructions())
        ->toEndWith('Never use emoji.');
});

it('points at the segments nothing is written to, and writes them in one go', function () {
    [$user, $project] = sequencer();

    $covered = TargetProfile::factory()->create(['project_id' => $project->id]);
    $bare = TargetProfile::factory()->create(['project_id' => $project->id]);

    Campaign::factory()->create([
        'project_id' => $project->id,
        'target_profile_id' => $covered->id,
    ]);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('campaigns.index'))
        ->assertOk()
        // A segment with no sequence does not appear on a list of sequences,
        // so nothing else on the page can point at it.
        ->assertInertia(fn ($page) => $page
            ->has('uncovered', 1)
            ->where('uncovered.0.id', $bare->id));

    Queue::fake();

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->post(route('campaigns.generate.missing'))
        ->assertRedirect();

    Queue::assertPushed(WriteCampaign::class, 1);
    Queue::assertPushed(fn (WriteCampaign $job): bool => $job->targetProfile->is($bare));
});

it('does not queue a second pass while one is still writing', function () {
    [, $project] = sequencer();

    TargetProfile::factory()->count(2)->create(['project_id' => $project->id]);

    Queue::fake();

    $first = app(WriteMissingCampaigns::class)->handle($project);

    // The tick comes round long before a minute-long write is finished, and
    // without the guard the same segments would be queued on every pass.
    $second = app(WriteMissingCampaigns::class)->handle($project);

    expect($first)->toHaveCount(2)
        ->and($second)->toHaveCount(0);

    Queue::assertPushed(WriteCampaign::class, 2);
});

it('writes nothing without a product portrait to write from', function () {
    [, $project] = sequencer();

    $project->update(['knowledge_base' => null]);
    TargetProfile::factory()->create(['project_id' => $project->id]);

    Queue::fake();

    // Queuing would burn one job per profile to raise the same error every
    // time, and the screen would show a failure that is really a missing step
    // two screens back.
    expect(app(WriteMissingCampaigns::class)->handle($project))->toHaveCount(0);

    Queue::assertNothingPushed();
});

it('writes the missing sequences by itself only when the project is left to itself', function () {
    [, $project] = sequencer();

    TargetProfile::factory()->create(['project_id' => $project->id]);

    Queue::fake();

    $this->artisan('eveil:write-missing')->assertSuccessful();

    Queue::assertNothingPushed();

    $project->update(['autonomy_level' => AutonomyLevel::Autonomous]);

    $this->artisan('eveil:write-missing')->assertSuccessful();

    Queue::assertPushed(WriteCampaign::class, 1);
});

it('splits one campaign across two pages, and each carries only its own', function () {
    [$user, $project] = sequencer();

    $campaign = campaignFor($project);

    // The mails and the run are read at different moments. One screen carrying
    // both meant scrolling past the run to reach the editor.
    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('campaigns/Show')
            ->has('campaign.steps')
            ->missing('pipeline')
            ->missing('sending')
            ->missing('leads'));

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('campaigns.delivery', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('campaigns/Delivery')
            ->has('sending')
            ->has('leads')
            // The steps are the other page's job, and personalising a preview
            // is a model call per lead: neither belongs on a screen about who
            // has been written to.
            ->missing('sample'));
});
