<?php

use App\Ai\Tools\CreateSequence;
use App\Ai\Tools\GetCampaign;
use App\Ai\Tools\GetDiscoveryRunStatus;
use App\Ai\Tools\ListCampaigns;
use App\Ai\Tools\ListCompanies;
use App\Ai\Tools\ListTargetProfiles;
use App\Ai\Tools\StartDiscovery;
use App\Ai\Tools\UpdateSequence;
use App\Enums\CampaignStatus;
use App\Enums\DiscoveryRunStatus;
use App\Jobs\Discovery\PlanDiscovery;
use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\Company;
use App\Models\DiscoveryRun;
use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;

/**
 * Every Evie tool is a thin adapter into an existing action or a scoped read
 * - never its own business logic. Verified by real effect (a real row
 * created/found), matching how this repo tests its other tools (RepliesTest),
 * not by mocking the action away.
 */
it('lists a project\'s own target profiles only', function () {
    $project = Project::factory()->create();
    $other = Project::factory()->create();

    TargetProfile::factory()->create(['project_id' => $project->id, 'name' => 'Mine']);
    TargetProfile::factory()->create(['project_id' => $other->id, 'name' => 'Not mine']);

    $result = (new ListTargetProfiles($project))->handle(new Request);

    expect($result)->toContain('Mine')->not->toContain('Not mine');
});

it('lists a project\'s own contactable companies only', function () {
    $project = Project::factory()->create();
    $other = Project::factory()->create();

    Company::factory()->create(['project_id' => $project->id, 'name' => 'Mine Co']);
    Company::factory()->create(['project_id' => $other->id, 'name' => 'Not Mine Co']);

    $result = (new ListCompanies($project))->handle(new Request);

    expect($result)->toContain('Mine Co')->not->toContain('Not Mine Co');
});

it('reports the discovery run status by target profile', function () {
    $project = Project::factory()->create();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);

    DiscoveryRun::factory()->create([
        'project_id' => $project->id,
        'target_profile_id' => $profile->id,
        'status' => DiscoveryRunStatus::Running,
        'candidates_found' => 7,
    ]);

    $result = (new GetDiscoveryRunStatus($project))->handle(new Request(['target_profile_id' => $profile->id]));

    expect($result)->toContain('"running"')->toContain('"candidates_found":7');
});

it('says so when nothing has been searched yet', function () {
    $project = Project::factory()->create();

    expect((new GetDiscoveryRunStatus($project))->handle(new Request))
        ->toBe('No discovery run has been started yet.');
});

it('delegates start_discovery to RunDiscovery::handle, never its own logic', function () {
    Queue::fake([PlanDiscovery::class]);

    $project = Project::factory()->create();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id, 'name' => 'Dental clinics']);

    $result = (new StartDiscovery($project))->handle(new Request(['target_profile_id' => $profile->id]));

    $run = DiscoveryRun::sole();

    expect($run->project_id)->toBe($project->id)
        ->and($run->target_profile_id)->toBe($profile->id)
        ->and($run->status)->toBe(DiscoveryRunStatus::Planning)
        ->and($result)->toContain((string) $run->id)
        ->and($result)->toContain('Dental clinics');

    Queue::assertPushed(PlanDiscovery::class);
});

it('refuses start_discovery for a target profile from another project', function () {
    $project = Project::factory()->create();
    $foreignProfile = TargetProfile::factory()->create();

    $result = (new StartDiscovery($project))->handle(new Request(['target_profile_id' => $foreignProfile->id]));

    expect($result)->toContain('No target profile with that id')
        ->and(DiscoveryRun::query()->count())->toBe(0);
});

it('delegates create_sequence to StoreSequence::handle - Evie\'s own arguments ARE the content, no writer agent involved', function () {
    $project = Project::factory()->create();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);

    $result = (new CreateSequence($project))->handle(new Request([
        'target_profile_id' => $profile->id,
        'name' => 'Widget outreach',
        'steps' => [
            ['type' => 'email', 'delay_hours' => 0, 'subject' => 'hi', 'body' => 'hello', 'intent' => 'open'],
        ],
    ]));

    expect($result)->toContain('Widget outreach')
        ->and(Campaign::sole())
        ->name->toBe('Widget outreach')
        ->target_profile_id->toBe($profile->id);
});

it('refuses create_sequence for a target profile from another project', function () {
    $project = Project::factory()->create();
    $foreignProfile = TargetProfile::factory()->create();

    $result = (new CreateSequence($project))->handle(new Request([
        'target_profile_id' => $foreignProfile->id,
        'name' => 'Widget outreach',
        'steps' => [
            ['type' => 'email', 'delay_hours' => 0, 'subject' => 'hi', 'body' => 'hello', 'intent' => 'open'],
        ],
    ]));

    expect($result)->toContain('No target profile with that id')
        ->and(Campaign::query()->count())->toBe(0);
});

it('surfaces an empty-steps error as a plain string, not an exception', function () {
    $project = Project::factory()->create();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);

    $result = (new CreateSequence($project))->handle(new Request([
        'target_profile_id' => $profile->id,
        'name' => 'Widget outreach',
        'steps' => [],
    ]));

    expect($result)->toContain('at least one step');
});

it('lists a project\'s own campaigns only', function () {
    $project = Project::factory()->create();
    $other = Project::factory()->create();

    Campaign::factory()->create(['project_id' => $project->id, 'name' => 'Mine']);
    Campaign::factory()->create(['project_id' => $other->id, 'name' => 'Not mine']);

    $result = (new ListCampaigns($project))->handle(new Request);

    expect($result)->toContain('Mine')->not->toContain('Not mine');
});

it('delegates update_sequence to UpdateSequence::handle, replacing the campaign\'s steps', function () {
    $project = Project::factory()->create();
    $campaign = Campaign::factory()->create(['project_id' => $project->id, 'name' => 'Old name']);
    CampaignStep::factory()->create(['campaign_id' => $campaign->id]);

    $result = (new UpdateSequence($project))->handle(new Request([
        'campaign_id' => $campaign->id,
        'name' => 'New name',
        'steps' => [
            ['type' => 'email', 'delay_hours' => 0, 'subject' => 'hi again', 'body' => 'hello again', 'intent' => 'follow up'],
        ],
    ]));

    expect($result)->toContain('New name')
        ->and($campaign->refresh())
        ->name->toBe('New name');

    expect($campaign->steps()->count())->toBe(1)
        ->and($campaign->steps()->first()->variants()->sole()->subject)->toBe('hi again');
});

it('refuses update_sequence on a campaign that already sent something', function () {
    $project = Project::factory()->create();
    $campaign = Campaign::factory()->create(['project_id' => $project->id, 'status' => CampaignStatus::Active]);
    CampaignStep::factory()->create(['campaign_id' => $campaign->id]);

    $result = (new UpdateSequence($project))->handle(new Request([
        'campaign_id' => $campaign->id,
        'steps' => [
            ['type' => 'email', 'delay_hours' => 0, 'subject' => 'hi', 'body' => 'hello', 'intent' => 'open'],
        ],
    ]));

    expect($result)->toContain('not a draft')
        ->and($campaign->steps()->count())->toBe(1);
});

it('refuses update_sequence for a campaign from another project', function () {
    $project = Project::factory()->create();
    $foreignCampaign = Campaign::factory()->create();

    $result = (new UpdateSequence($project))->handle(new Request([
        'campaign_id' => $foreignCampaign->id,
        'steps' => [
            ['type' => 'email', 'delay_hours' => 0, 'subject' => 'hi', 'body' => 'hello', 'intent' => 'open'],
        ],
    ]));

    expect($result)->toContain('No campaign with that id');
});

it('reads a campaign\'s full content: subject, body and timing per step', function () {
    $project = Project::factory()->create();
    $campaign = Campaign::factory()->create(['project_id' => $project->id, 'name' => 'Pizzeria outreach']);

    $emailStep = CampaignStep::factory()->create([
        'campaign_id' => $campaign->id,
        'position' => 1,
        'type' => 'email',
        'delay_hours' => null,
        'config' => ['intent' => 'open with a compliment on their menu'],
    ]);
    $emailStep->variants()->create(['subject' => 'quick question about your menu', 'body' => "Hi there,\n\nI noticed...", 'weight' => 1]);

    $waitStep = CampaignStep::factory()->create([
        'campaign_id' => $campaign->id,
        'position' => 2,
        'type' => 'wait',
        'delay_hours' => 72,
        'config' => ['intent' => 'give them time to reply'],
    ]);

    $result = json_decode((new GetCampaign($project))->handle(new Request(['campaign_id' => $campaign->id])), true);

    expect($result['name'])->toBe('Pizzeria outreach')
        ->and($result['steps'])->toHaveCount(2)
        ->and($result['steps'][0])->toMatchArray([
            'position' => 1,
            'type' => 'email',
            'intent' => 'open with a compliment on their menu',
            'subject' => 'quick question about your menu',
        ])
        ->and($result['steps'][0]['body'])->toContain('I noticed')
        ->and($result['steps'][1])->toMatchArray([
            'position' => 2,
            'type' => 'wait',
            'delay_hours' => 72,
            'intent' => 'give them time to reply',
        ])
        ->and($result['steps'][1])->not->toHaveKey('subject');
});

it('refuses get_campaign for a campaign from another project', function () {
    $project = Project::factory()->create();
    $foreignCampaign = Campaign::factory()->create();

    $result = (new GetCampaign($project))->handle(new Request(['campaign_id' => $foreignCampaign->id]));

    expect($result)->toContain('No campaign with that id');
});
