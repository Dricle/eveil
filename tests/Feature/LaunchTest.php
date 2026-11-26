<?php

use App\Actions\DraftLaunchCopy;
use App\Actions\PrepareLaunch;
use App\Actions\SyncLaunchCatalog;
use App\Ai\Agents\LaunchCopywriter;
use App\Ai\Agents\LaunchPlanner;
use App\Ai\Tools\ListLaunchVenues;
use App\Ai\Tools\UpdateLaunchVenue;
use App\Enums\AgentRunStatus;
use App\Enums\LaunchVenueOrigin;
use App\Jobs\DraftLaunchCopy as DraftLaunchCopyJob;
use App\Jobs\PrepareLaunch as PrepareLaunchJob;
use App\Models\AgentRun;
use App\Models\LaunchItem;
use App\Models\LaunchSubmission;
use App\Models\LaunchVenue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Models\User;
use App\Support\CurrentProject;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Tools\Request;

/**
 * @return array{0: User, 1: Project}
 */
function launchSetup(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();

    app(CurrentProject::class)->set($project);
    forProject($project);

    return [$user, $project];
}

function launchItemFor(Project $project, array $venue = []): LaunchItem
{
    return LaunchItem::factory()->create([
        'project_id' => $project->id,
        'launch_venue_id' => LaunchVenue::factory()->create($venue)->id,
    ]);
}

describe('preparing a launch', function () {
    it('lists the catalog venues whose tags the product has, and the subreddits its profiles found', function () {
        [, $project] = launchSetup();
        TargetProfile::factory()->create([
            'project_id' => $project->id,
            'criteria' => ['subreddits' => [['name' => 'selfhosted'], ['name' => 'SaaS']]],
        ]);
        LaunchPlanner::fake([['reason' => 'A web app for teams.', 'tags' => ['software', 'b2b']]]);

        app(PrepareLaunch::class)->handle($project);

        $keys = LaunchItem::query()->with('venue')->get()->pluck('venue.key');

        expect($project->fresh()->launch_tags)->toBe(['software', 'b2b'])
            ->and($keys)->toContain('producthunt.com', 'g2.com', 'reddit.com/r/selfhosted', 'reddit.com/r/saas')
            ->and($keys)->not->toContain('futuretools.io', 'devhunt.org')
            ->and($keys->filter(fn (string $key): bool => $key === 'reddit.com/r/saas'))->toHaveCount(1);

        $subreddit = LaunchVenue::query()->where('project_id', $project->id)->sole();
        expect($subreddit->origin)->toBe(LaunchVenueOrigin::Ai)
            ->and($subreddit->cooldown_days)->toBe(LaunchVenue::SUBREDDIT_COOLDOWN_DAYS);
    });

    it('keeps what was done on a venue when prepared again', function () {
        [, $project] = launchSetup();
        LaunchPlanner::fake([
            ['reason' => 'A web app.', 'tags' => ['software']],
            ['reason' => 'A web app.', 'tags' => ['software']],
        ]);

        app(PrepareLaunch::class)->handle($project);
        $item = LaunchItem::query()->whereRelation('venue', 'key', 'producthunt.com')->sole();
        $item->update(['draft' => 'My tagline', 'dismissed_at' => now()]);

        app(PrepareLaunch::class)->handle($project);

        expect(LaunchItem::query()->whereRelation('venue', 'key', 'producthunt.com')->sole())
            ->id->toBe($item->id)
            ->draft->toBe('My tagline')
            ->dismissed_at->not->toBeNull();
    });

    it('queues the planner with a pending run', function () {
        [$user] = launchSetup();
        Queue::fake();

        $this->actingAs($user)->from(route('launch.index'))->post(route('launch.preparation.store'))
            ->assertRedirect(route('launch.index'));

        expect(AgentRun::query()->sole())
            ->agent->toBe('launch-planner')
            ->status->toBe(AgentRunStatus::Pending);
        Queue::assertPushed(PrepareLaunchJob::class);
    });
});

it('retires a catalog venue the catalog file no longer lists, and leaves project venues alone', function () {
    [, $project] = launchSetup();
    $retired = LaunchVenue::factory()->create(['key' => 'gone.example']);
    $custom = LaunchVenue::factory()->custom($project)->create();

    app(SyncLaunchCatalog::class)->handle();

    expect($retired->fresh()->trashed())->toBeTrue()
        ->and($custom->fresh()->trashed())->toBeFalse()
        ->and(LaunchVenue::query()->whereNull('project_id')->where('key', 'producthunt.com')->exists())->toBeTrue();
});

describe('posting', function () {
    it('marks a venue posted with the link to the post', function () {
        [$user, $project] = launchSetup();
        $item = launchItemFor($project);

        $this->actingAs($user)->from(route('launch.index'))
            ->post(route('launch.items.submissions.store', $item), ['url' => 'https://example.com/my-launch'])
            ->assertRedirect(route('launch.index'));

        expect($item->submissions()->sole()->url)->toBe('https://example.com/my-launch');
    });

    it('refuses to mark a venue posted without a link', function () {
        [$user, $project] = launchSetup();
        $item = launchItemFor($project);

        $this->actingAs($user)->from(route('launch.index'))
            ->post(route('launch.items.submissions.store', $item), ['url' => ''])
            ->assertSessionHasErrors(['url' => 'The url field is required.']);

        expect(LaunchSubmission::query()->count())->toBe(0);
    });

    it('brings a venue back to do once its cooldown has elapsed', function () {
        [$user, $project] = launchSetup();
        $once = launchItemFor($project, ['name' => 'Once', 'cooldown_days' => null]);
        $again = launchItemFor($project, ['name' => 'Again', 'cooldown_days' => 30]);
        LaunchSubmission::factory()->create(['launch_item_id' => $once->id, 'posted_at' => now()->subDays(40)]);
        LaunchSubmission::factory()->create(['launch_item_id' => $again->id, 'posted_at' => now()->subDays(40)]);

        $this->actingAs($user)->get(route('launch.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('launch/Index')
                ->where('items.0.venue.name', 'Once')
                ->where('items.0.state', 'posted')
                ->where('items.1.venue.name', 'Again')
                ->where('items.1.state', 'todo')
                ->has('items.1.posts', 1));
    });

    it('shows a recent post on a cooldown venue as posted, with the date it can be posted again', function () {
        [$user, $project] = launchSetup();
        $item = launchItemFor($project, ['cooldown_days' => 30]);
        LaunchSubmission::factory()->create(['launch_item_id' => $item->id, 'posted_at' => '2026-11-01 10:00:00']);
        $this->travelTo('2026-11-10');

        $this->actingAs($user)->get(route('launch.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.state', 'posted')
                ->where('items.0.available_again_on', '2026-12-01'));
    });
});

it('dismisses a venue and restores it', function () {
    [$user, $project] = launchSetup();
    $item = launchItemFor($project);

    $this->actingAs($user)->from(route('launch.index'))->patch(route('launch.items.update', $item), ['dismissed' => true]);
    expect($item->fresh()->dismissed_at)->not->toBeNull();

    $this->actingAs($user)->from(route('launch.index'))->patch(route('launch.items.update', $item), ['dismissed' => false]);
    expect($item->fresh()->dismissed_at)->toBeNull();
});

describe('custom venues', function () {
    it('adds a venue to the checklist, once per address', function () {
        [$user, $project] = launchSetup();
        $venue = ['name' => 'Niche list', 'url' => 'https://www.niche.example/submit/', 'kind' => 'directory', 'cooldown_days' => 60];

        $this->actingAs($user)->from(route('launch.index'))->post(route('launch.venues.store'), $venue)
            ->assertRedirect(route('launch.index'));
        $this->actingAs($user)->from(route('launch.index'))->post(route('launch.venues.store'), [...$venue, 'url' => 'https://niche.example/submit']);

        expect(LaunchItem::query()->with('venue')->sole()->venue)
            ->project_id->toBe($project->id)
            ->origin->toBe(LaunchVenueOrigin::User)
            ->key->toBe('niche.example/submit')
            ->cooldown_days->toBe(60);
    });

    it('refuses a venue without an address', function () {
        [$user] = launchSetup();

        $this->actingAs($user)->from(route('launch.index'))
            ->post(route('launch.venues.store'), ['name' => 'Niche list', 'url' => 'not a url', 'kind' => 'directory'])
            ->assertSessionHasErrors(['url' => 'The url field must be a valid URL.']);

        expect(LaunchVenue::query()->count())->toBe(0);
    });

    it('removes a custom venue from the checklist and keeps its posts', function () {
        [$user, $project] = launchSetup();
        $item = LaunchItem::factory()->create([
            'project_id' => $project->id,
            'launch_venue_id' => LaunchVenue::factory()->custom($project)->create()->id,
        ]);
        LaunchSubmission::factory()->create(['launch_item_id' => $item->id]);

        $this->actingAs($user)->from(route('launch.index'))->delete(route('launch.venues.destroy', $item));

        expect($item->venue->fresh()->trashed())->toBeTrue()
            ->and($item->submissions()->count())->toBe(1);
        $this->actingAs($user)->get(route('launch.index'))
            ->assertInertia(fn (Assert $page) => $page->has('items', 0));
    });

    it('never removes a catalog venue', function () {
        [$user, $project] = launchSetup();
        $item = launchItemFor($project);

        $this->actingAs($user)->delete(route('launch.venues.destroy', $item))->assertNotFound();

        expect($item->venue->fresh()->trashed())->toBeFalse();
    });
});

describe('copy', function () {
    it('queues the copywriter for one venue and points the row at its run', function () {
        [$user, $project] = launchSetup();
        Queue::fake();
        $item = launchItemFor($project);

        $this->actingAs($user)->from(route('launch.index'))->post(route('launch.items.draft', $item));

        $run = AgentRun::query()->sole();
        expect($run->agent)->toBe('launch-copywriter')
            ->and($item->fresh()->agent_run_id)->toBe($run->id);
        Queue::assertPushed(DraftLaunchCopyJob::class);
    });

    it('keeps the written copy on the row, one block per field', function () {
        [, $project] = launchSetup();
        $item = launchItemFor($project, ['rules' => 'A tagline of 60 characters at most.']);
        LaunchCopywriter::fake([['fields' => [
            ['name' => 'Tagline', 'text' => "Send cold email from your own inbox\n", 'max_characters' => 60],
            ['name' => 'Categories', 'text' => 'Email, Sales', 'max_characters' => 0],
        ]]]);

        app(DraftLaunchCopy::class)->handle($item);

        expect($item->fresh()->draft)->toBe("Tagline:\nSend cold email from your own inbox\n\nCategories:\nEmail, Sales");
    });

    it('sends a field over its limit back once to be shortened', function () {
        [, $project] = launchSetup();
        $item = launchItemFor($project);
        LaunchCopywriter::fake([
            ['fields' => [['name' => 'Tagline', 'text' => str_repeat('a', 61), 'max_characters' => 60]]],
            ['fields' => [['name' => 'Tagline', 'text' => 'Short enough', 'max_characters' => 60]]],
        ]);

        app(DraftLaunchCopy::class)->handle($item);

        expect($item->fresh()->draft)->toBe("Tagline:\nShort enough");
        LaunchCopywriter::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '61 characters, limit 60'));
    });
});

it('answers 404 for another project\'s checklist row and changes nothing', function () {
    [$user] = launchSetup();
    $foreign = launchItemFor(Project::factory()->create());

    $this->actingAs($user)->patch(route('launch.items.update', $foreign), ['dismissed' => true])->assertNotFound();
    $this->actingAs($user)->post(route('launch.items.submissions.store', $foreign), ['url' => 'https://example.com'])->assertNotFound();

    expect($foreign->fresh()->dismissed_at)->toBeNull()
        ->and(LaunchSubmission::query()->count())->toBe(0);
});

describe('tools', function () {
    it('lists what is still to do, one kind at a time', function () {
        [, $project] = launchSetup();
        $todo = launchItemFor($project, ['name' => 'Todo list', 'rules' => 'Short tagline.']);
        $posted = launchItemFor($project, ['name' => 'Posted list']);
        LaunchSubmission::factory()->create(['launch_item_id' => $posted->id]);
        launchItemFor($project, ['name' => 'Big platform', 'kind' => 'launch_platform']);

        $result = json_decode((string) (new ListLaunchVenues($project))->handle(new Request(['kind' => 'directory'])), true);

        expect($result['counts_by_kind'])->toBe(['directory' => 1, 'launch_platform' => 1])
            ->and($result['venues'])->toHaveCount(1)
            ->and($result['venues'][0])->toMatchArray(['id' => $todo->id, 'name' => 'Todo list', 'rules' => 'Short tagline.']);
    });

    it('marks a venue posted only with a link', function () {
        [, $project] = launchSetup();
        $item = launchItemFor($project);
        $tool = new UpdateLaunchVenue($project);

        $refused = (string) $tool->handle(new Request(['launch_item_id' => $item->id, 'action' => 'posted']));
        $tool->handle(new Request(['launch_item_id' => $item->id, 'action' => 'posted', 'url' => 'https://example.com/post']));

        expect($refused)->toContain('needs the link')
            ->and($item->submissions()->sole()->url)->toBe('https://example.com/post');
    });

    it('never touches another project\'s venue', function () {
        [, $project] = launchSetup();
        $foreign = launchItemFor(Project::factory()->create());

        $result = (string) (new UpdateLaunchVenue($project))->handle(new Request(['launch_item_id' => $foreign->id, 'action' => 'dismiss']));

        expect($result)->toContain('No launch venue')
            ->and($foreign->fresh()->dismissed_at)->toBeNull();
    });
});

it('serves the checklist over the API, and records a post on it', function () {
    $project = Project::factory()->create();
    $item = launchItemFor($project, ['name' => 'Niche list']);
    $foreign = launchItemFor(Project::factory()->create());
    $token = Str::after($project->organization->createToken('test')->plainTextToken, '|');

    $this->withToken($token)->getJson(route('api.launch-venues.index', $project))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.venue.name', 'Niche list');
    $this->withToken($token)->postJson(route('api.launch-venues.submissions.store', [$project, $item->id]), ['url' => 'https://example.com/post'])
        ->assertJsonPath('state', 'posted');
    $this->withToken($token)->patchJson(route('api.launch-venues.update', [$project, $foreign->id]), ['dismissed' => true])
        ->assertNotFound();
});
