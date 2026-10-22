<?php

use App\Ai\Tools\SetChannel;
use App\Enums\Channel;
use App\Enums\RedditScanFrequency;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostFrequency;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Laravel\Ai\Tools\Request;

/**
 * A channel switched on or off as a whole, from the onboarding or the project
 * settings. Each one maps onto the column that already decides whether it runs.
 */
function channelOwner(): array
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);

    $project = Project::factory()->for($user->organizations()->sole())->create();
    test()->actingAs($user);
    forProject($project);

    return [$user, $project];
}

it('switches a channel on weekly and due now, and off again', function () {
    [, $project] = channelOwner();
    $this->travelTo(now()->setTime(12, 0));

    $this->put(route('settings.channels.update', 'reddit'), ['enabled' => true])->assertRedirect();

    $project->refresh();
    expect($project->reddit_scan_frequency)->toBe(RedditScanFrequency::Weekly)
        ->and($project->reddit_next_scan_at->equalTo(now()))->toBeTrue();

    $this->put(route('settings.channels.update', 'reddit'), ['enabled' => false]);

    expect($project->refresh()->reddit_scan_frequency)->toBe(RedditScanFrequency::Off);
});

it('keeps the cadence a user chose when the channel is already on', function () {
    [, $project] = channelOwner();
    $project->update(['linkedin_post_frequency' => SocialPostFrequency::Daily]);

    $this->put(route('settings.channels.update', 'linkedin'), ['enabled' => true]);

    expect($project->refresh()->linkedin_post_frequency)->toBe(SocialPostFrequency::Daily);
});

it('switches cold email off without touching anything else', function () {
    [, $project] = channelOwner();

    $this->putJson(route('settings.channels.update', 'email'), ['enabled' => false]);

    $project->refresh();
    expect($project->email_enabled)->toBeFalse()
        ->and($project->reddit_scan_frequency)->toBe(RedditScanFrequency::Off);
});

it('refuses a switch with no state, and a channel that does not exist', function () {
    channelOwner();

    $this->put(route('settings.channels.update', 'email'), [])->assertSessionHasErrors('enabled');
    $this->put(route('settings.channels.update', 'fax'), ['enabled' => true])->assertNotFound();
});

it('shows every channel on the project settings, with the reason it fits or not', function () {
    [, $project] = channelOwner();
    $project->update(['knowledge_base' => ['channel_fit' => [
        ['channel' => 'reddit', 'recommended' => true, 'reason' => 'Developers compare tools on r/selfhosted.'],
        ['channel' => 'instagram', 'recommended' => false, 'reason' => 'Nothing to show in a picture.'],
    ]]]);

    $this->get(route('settings.project.edit'))
        ->assertInertia(fn ($page) => $page
            ->has('channels', count(Channel::cases()))
            ->where('channels.0.key', 'email')
            ->where('channels.0.enabled', true)
            ->where('channels.1.key', 'reddit')
            ->where('channels.1.recommended', true)
            ->where('channels.1.reason', 'Developers compare tools on r/selfhosted.')
            ->where('channels.5.key', 'instagram')
            ->where('channels.5.recommended', false)
            ->where('channels.5.reason', 'Nothing to show in a picture.'));
});

it('lets Evie switch a channel, and names the channels when asked for one that does not exist', function () {
    $project = Project::factory()->create();
    $tool = new SetChannel($project);

    expect((string) $tool->handle(new Request(['channel' => 'email', 'enabled' => false])))->toBe('Cold email is off.')
        ->and($project->refresh()->email_enabled)->toBeFalse()
        ->and((string) $tool->handle(new Request(['channel' => 'fax', 'enabled' => true])))->toContain('reddit, linkedin');
});

it('has a channel for every social network', function (SocialPlatform $platform) {
    // An enum cannot borrow another's cases, so a network added to
    // `SocialPlatform` has to be added to `Channel` too, or it never gets a
    // switch and nothing says so.
    expect(Channel::tryFrom($platform->value))->not->toBeNull();
})->with(SocialPlatform::cases());
