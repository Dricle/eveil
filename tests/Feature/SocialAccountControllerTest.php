<?php

use App\Ai\Tools\DraftSocialPost;
use App\Ai\Tools\UpdateSocialPost;
use App\Enums\AutonomyLevel;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Jobs\GenerateSocialPost;
use App\Models\Organization;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\CurrentProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;

function socialAccountSetup(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();

    app(CurrentProject::class)->set($project);

    return [$user, $project];
}

it('connects a Bluesky account, keeps the app password encrypted, and grants it to this project', function () {
    [$user, $project] = socialAccountSetup();
    Http::fake([
        'bsky.social/xrpc/com.atproto.server.createSession' => Http::response(['accessJwt' => 'jwt', 'did' => 'did:plc:acme', 'handle' => 'acme.bsky.social']),
        'public.api.bsky.app/*' => Http::response(['displayName' => 'Acme']),
    ]);

    $this->actingAs($user)->post(route('settings.social.store'), ['handle' => '@acme.bsky.social', 'app_password' => 'abcd-efgh-ijkl-mnop'])
        ->assertRedirect(route('settings.social.index'));

    $account = SocialAccount::sole();

    expect($account)
        ->handle->toBe('acme.bsky.social')
        ->external_id->toBe('did:plc:acme')
        ->display_name->toBe('Acme')
        ->secret->toBe('abcd-efgh-ijkl-mnop')
        ->and(DB::table('social_accounts')->value('secret'))->not->toBe('abcd-efgh-ijkl-mnop')
        ->and($account->projects->pluck('id')->all())->toBe([$project->id]);
});

it('shows Bluesky\'s refusal on the password field and stores nothing', function () {
    [$user] = socialAccountSetup();
    Http::fake(['bsky.social/*' => Http::response(['message' => 'Invalid identifier or password'], 401)]);

    $this->actingAs($user)->from(route('settings.social.index'))
        ->post(route('settings.social.store'), ['handle' => 'acme.bsky.social', 'app_password' => 'wrong'])
        ->assertSessionHasErrors('app_password');

    expect(SocialAccount::count())->toBe(0);
});

it('cannot change or remove another organization\'s account', function () {
    [$user] = socialAccountSetup();
    $foreign = SocialAccount::factory()->create();

    $this->actingAs($user)->put(route('settings.social.update', $foreign), ['projects' => []])->assertNotFound();
    $this->actingAs($user)->delete(route('settings.social.destroy', $foreign))->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});

it('saves the Bluesky autonomy and the X and Bluesky tone box', function () {
    [$user, $project] = socialAccountSetup();

    $this->actingAs($user)->put(route('settings.autonomy.update'), [
        'email_autonomy_level' => 'supervised',
        'linkedin_autonomy_level' => 'supervised',
        'bluesky_autonomy_level' => 'autonomous',
    ])->assertRedirect(route('settings.autonomy.edit'));

    $this->actingAs($user)->put(route('settings.ai-instructions.social.update'), ['social_prompt_instructions' => 'No hashtags.'])
        ->assertRedirect(route('settings.ai-instructions.edit'));

    expect($project->fresh())
        ->bluesky_autonomy_level->toBe(AutonomyLevel::Autonomous)
        ->social_prompt_instructions->toBe('No hashtags.');
});

it('lets Evie queue a post from a brief, for the network she names', function () {
    Queue::fake();
    $project = Project::factory()->create();

    (new DraftSocialPost($project))->handle(new Request(['platform' => 'bluesky', 'brief' => 'We shipped dark mode.']));

    Queue::assertPushed(GenerateSocialPost::class, fn (GenerateSocialPost $job): bool => $job->platform === SocialPlatform::Bluesky
        && $job->brief === 'We shipped dark mode.'
        && $job->project->is($project));
});

it('lets Evie edit only a draft', function () {
    $project = Project::factory()->create();
    $draft = SocialPost::factory()->create(['project_id' => $project->id]);

    (new UpdateSocialPost($project))->handle(new Request(['social_post_id' => $draft->id, 'body' => 'Shorter.']));
    expect($draft->fresh()->body)->toBe('Shorter.');

    $draft->update(['status' => SocialPostStatus::Published]);
    $answer = (new UpdateSocialPost($project))->handle(new Request(['social_post_id' => $draft->id, 'body' => 'Again.']));

    expect((string) $answer)->toContain('already published')
        ->and($draft->fresh()->body)->toBe('Shorter.');
});
