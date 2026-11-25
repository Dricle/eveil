<?php

use App\Actions\FindSubreddits;
use App\Ai\Tools\UpdateTargetProfile;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

/**
 * Arctic Shift answers an exact-name lookup with whichever of `$known` matches, case-insensitively.
 *
 * @param  array<string, int>  $known  name => subscribers
 */
function fakeReddit(array $known): void
{
    Http::fake([
        'arctic-shift.photon-reddit.com/*' => function ($request) use ($known) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $name = $query['subreddit'] ?? $query['subreddit_prefix'] ?? '';

            return Http::response(['data' => collect($known)
                ->filter(fn (int $subscribers, string $known): bool => strcasecmp($known, $name) === 0)
                ->map(fn (int $subscribers, string $known): array => ['display_name' => $known, 'subscribers' => $subscribers, 'public_description' => '', 'quarantine' => false])
                ->values()
                ->all()]);
        },
        'searxng:8080/*' => Http::response(['results' => []]),
    ]);
}

it('adds a subreddit checked against reddit, and refuses one it does not know', function () {
    fakeReddit(['micro_saas' => 30_000]);
    $project = Project::factory()->create();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id, 'criteria' => ['subreddits' => [['name' => 'SaaS', 'subscribers' => 200_000, 'description' => '']]]]);

    $result = (string) (new UpdateTargetProfile($project))->handle(new Request([
        'target_profile_id' => $profile->id,
        'add_subreddits' => ['https://www.reddit.com/r/micro_saas/', 'r/notarealone'],
    ]));

    expect($result)->toContain('Not added')->toContain('notarealone')
        ->and(array_column($profile->fresh()->criteria['subreddits'], 'name'))->toBe(['micro_saas', 'SaaS']);
});

it('keeps a hand-added subreddit through a re-check', function () {
    $nine = collect(range(1, 9))->mapWithKeys(fn (int $i): array => ["big{$i}" => 1_000_000 + $i])->all();
    fakeReddit([...$nine, 'micro_saas' => 30_000]);
    $project = Project::factory()->create();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id, 'criteria' => ['subreddit_guesses' => array_keys($nine)]]);

    (new UpdateTargetProfile($project))->handle(new Request(['target_profile_id' => $profile->id, 'add_subreddits' => ['micro_saas']]));
    $resolved = app(FindSubreddits::class)->handle($profile->fresh());

    expect(array_column($resolved->criteria['subreddits'], 'name'))->toContain('micro_saas')->toHaveCount(9);
});

it('removes subreddits whatever their case or prefix', function () {
    $project = Project::factory()->create();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id, 'criteria' => [
        'subreddits' => [['name' => 'SaaS', 'subscribers' => 200_000, 'description' => ''], ['name' => 'micro_saas', 'subscribers' => 30_000, 'description' => '']],
    ]]);

    (new UpdateTargetProfile($project))->handle(new Request(['target_profile_id' => $profile->id, 'remove_subreddits' => ['r/MICRO_SAAS', 'saas']]));

    expect($profile->fresh()->criteria['subreddits'])->toBe([]);
});

it('leaves subreddits untouched when an update names none', function () {
    $project = Project::factory()->create();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id, 'criteria' => ['sectors' => ['saas']]]);

    (new UpdateTargetProfile($project))->handle(new Request(['target_profile_id' => $profile->id, 'name' => 'Renamed']));

    expect($profile->fresh()->criteria)->not->toHaveKey('subreddits');
});

it('adds and removes a subreddit from the profile page, only on this project', function () {
    fakeReddit(['micro_saas' => 30_000]);
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id, 'criteria' => []]);
    $foreign = TargetProfile::factory()->create(['criteria' => []]);

    $this->actingAs($user)->post(route('targets.subreddit.store', ['project' => $project->slug, 'target' => $profile->id]), ['name' => 'nope'])
        ->assertSessionHasErrors('name');
    $this->post(route('targets.subreddit.store', ['project' => $project->slug, 'target' => $profile->id]), ['name' => 'micro_saas'])
        ->assertSessionHasNoErrors();
    $this->post(route('targets.subreddit.store', ['project' => $project->slug, 'target' => $foreign->id]), ['name' => 'micro_saas'])
        ->assertNotFound();

    expect(array_column($profile->fresh()->criteria['subreddits'], 'name'))->toBe(['micro_saas']);

    $this->delete(route('targets.subreddit.destroy', ['project' => $project->slug, 'target' => $profile->id, 'subreddit' => 'micro_saas']))
        ->assertRedirect();

    expect($profile->fresh()->criteria['subreddits'])->toBe([]);
});
