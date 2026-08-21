<?php

use App\Enums\AgentRunStatus;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisType;
use App\Jobs\DeriveTargets;
use App\Models\AgentRun;
use App\Models\DiscoveryRun;
use App\Models\EmailAccount;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectAnalysis;
use App\Models\TargetProfile;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Enums\Lab;

/**
 * @return array{0: User, 1: Project}
 */
function newcomer(): array
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);

    return [$user, Project::factory()->for($user->organizations()->sole())->create()];
}

function visiting(User $user, Project $project): TestResponse
{
    return test()->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('onboarding'));
}

beforeEach(function () {
    Queue::fake();
});

it('sends somebody who just created a project into the guided run, not to an empty dashboard', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response('<!doctype html><html lang="en"><head><title>Acme</title></head><body><p>Acme sells things.</p></body></html>'),
    ]);
    app(Settings::class)->set('crawl.delay_ms', 0);

    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => 'Acme', 'url' => 'https://acme.test'])
        // A dashboard of zeroes at this moment reads as a product that does
        // nothing; the site is being read right now and that is worth watching.
        ->assertRedirect(route('onboarding'));
});

it('shows the crawl running, then what it understood, and nothing else', function () {
    [$user, $project] = newcomer();

    $analysis = ProjectAnalysis::factory()->create([
        'project_id' => $project->id,
        'type' => AnalysisType::Website,
        'status' => AnalysisStatus::Running,
        'raw' => ['max_pages' => 10, 'pages' => ['a', 'b']],
    ]);

    visiting($user, $project)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding')
            ->where('analysis.running', true)
            ->where('analysis.pages_read', 2)
            ->where('analysis.pages_planned', 10)
            ->where('knowledgeBase', null)
            ->has('profiles', 0));

    $analysis->update(['status' => AnalysisStatus::Succeeded]);
    $project->update(['knowledge_base' => ['what_it_does' => 'Commission-free ordering for restaurants.']]);

    visiting($user, $project)
        ->assertInertia(fn ($page) => $page
            ->where('analysis.running', false)
            ->where('knowledgeBase.what_it_does', 'Commission-free ordering for restaurants.'));
});

it('starts the segment derivation when the portrait is agreed to', function () {
    [$user, $project] = newcomer();

    $project->update(['knowledge_base' => ['what_it_does' => 'Ordering for restaurants.']]);

    // The same route the Targets screen uses: agreeing here is not a different
    // operation, it is the same one reached from the run.
    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->post(route('targets.derive'))
        ->assertRedirect();

    Queue::assertPushed(DeriveTargets::class);

    // And while it runs, the screen says so rather than looking finished.
    AgentRun::query()->create([
        'project_id' => $project->id,
        'agent' => 'target-profile-deriver',
        'status' => AgentRunStatus::Pending,
    ]);

    visiting($user, $project)->assertInertia(fn ($page) => $page->where('deriving', true));
});

it('starts one search per segment left switched on, and never a second for the same one', function () {
    [$user, $project] = newcomer();

    $wanted = TargetProfile::factory()->create(['project_id' => $project->id, 'is_active' => true]);
    $alsoWanted = TargetProfile::factory()->create(['project_id' => $project->id, 'is_active' => true]);
    $paused = TargetProfile::factory()->create(['project_id' => $project->id, 'is_active' => false]);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->post(route('onboarding.searches'))
        ->assertRedirect();

    $searched = DiscoveryRun::query()->pluck('target_profile_id')->sort()->values()->all();

    expect($searched)->toBe([$wanted->id, $alsoWanted->id])
        ->and($searched)->not->toContain($paused->id);

    // Clicking twice must not double the bill: a profile already being searched
    // is skipped.
    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->post(route('onboarding.searches'));

    expect(DiscoveryRun::query()->count())->toBe(2);
});

it('says the run is under way once a search exists', function () {
    [$user, $project] = newcomer();

    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);
    DiscoveryRun::factory()->create(['project_id' => $project->id, 'target_profile_id' => $profile->id]);

    visiting($user, $project)->assertInertia(fn ($page) => $page->where('searches', 1));
});

it('warns a superadmin with no provider key, and nobody else', function () {
    [$user, $project] = newcomer();

    // The suite runs with a dummy key in the environment so a stray call would
    // 401 rather than bill; an instance nobody has configured has none at all.
    foreach (Lab::cases() as $lab) {
        config(["ai.providers.{$lab->value}.key" => '']);
    }

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        // Instance scope: nobody but the superadmin can fix it, and a permanent
        // banner about somebody else's job is noise.
        ->assertInertia(fn ($page) => $page->where('setup.provider', false));

    $user->forceFill(['is_super_admin' => true])->save();

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('setup.provider', true));
});

it('warns while the project has no mailbox, and stops once it has one', function () {
    [$user, $project] = newcomer();

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('setup.mailbox', true));

    $mailbox = EmailAccount::factory()->for($user->organizations()->sole())->create();
    $mailbox->projects()->attach($project);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        // Attached to THIS project: a mailbox the organization owns but has not
        // granted here still cannot send for it.
        ->assertInertia(fn ($page) => $page->where('setup.mailbox', false));
});

it('offers the way back into the run from the dashboard, until a search exists', function () {
    [$user, $project] = newcomer();

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('onboarding', true));

    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);
    DiscoveryRun::factory()->create(['project_id' => $project->id, 'target_profile_id' => $profile->id]);

    // Once something has been searched for, the dashboard has real numbers to
    // show and the prompt would just be in the way.
    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('onboarding', false));
});

it('asks what the site never said, at the moment the portrait is reviewed', function () {
    [$user, $project] = newcomer();

    ProjectAnalysis::factory()->create([
        'project_id' => $project->id,
        'type' => AnalysisType::Website,
        'status' => AnalysisStatus::Succeeded,
    ]);

    $project->update(['knowledge_base' => [
        'what_it_does' => 'Ordering for restaurants.',
        'gaps' => [['key' => 'service_area', 'question' => 'Which countries do you deliver to?']],
    ]]);

    // Answered here rather than left for a settings screen: the answer feeds
    // the segments the next button on this page derives.
    visiting($user, $project)
        ->assertInertia(fn ($page) => $page
            ->where('openQuestions.0.key', 'service_area')
            ->where('openQuestions.0.question', 'Which countries do you deliver to?')
            ->where('openQuestions.0.answer', null));
});
