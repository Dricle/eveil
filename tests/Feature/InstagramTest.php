<?php

use App\Ai\Agents\ImageGenerator;
use App\Ai\Agents\SocialPostWriter;
use App\Ai\Tools\RequestSocialPostImage;
use App\Ai\Tools\SelectSocialPostImage;
use App\Enums\AgentRunStatus;
use App\Enums\AutonomyLevel;
use App\Enums\IntegrationStatus;
use App\Enums\PlatformAppKind;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Jobs\GenerateSocialPost;
use App\Jobs\GenerateSocialPostImage;
use App\Models\AgentRun;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\PlatformApp;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\CurrentProject;
use App\Support\SocialPostProgress;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Image;
use Laravel\Ai\Prompts\ImagePrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;
use Laravel\Ai\Tools\Request as ToolRequest;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

const COMPOSIO = 'backend.composio.dev/api/v3.1';

/**
 * @return array{0: User, 1: Project}
 */
function instagramSetup(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $organization->update(['composio_api_key' => 'ak_composio-test-key']);
    $project = Project::factory()->for($organization)->create();

    app(CurrentProject::class)->set($project);

    return [$user, $project];
}

/**
 * A real image, base64: the generator decodes it to re-encode it as JPEG.
 */
function instagramPng(): string
{
    $image = imagecreatetruecolor(8, 8);
    ob_start();
    imagepng($image);

    return base64_encode((string) ob_get_clean());
}

function instagramImage(SocialPost $post, string $bytes = 'jpeg-bytes', string $fileName = 'a.jpg'): Media
{
    return $post->addMediaFromString($bytes)->usingFileName($fileName)->withCustomProperties(['brief' => 'A brief.'])->toMediaCollection('images');
}

function fakeInstagramWriter(array $structured): void
{
    SocialPostWriter::fake([new StructuredTextResponse(
        $structured,
        '{}',
        new TextUsage(inputTokens: 10, outputTokens: 5),
        new Meta('anthropic', 'claude-opus-5'),
    )]);
}

function fakeComposioPublishing(): void
{
    Http::preventStrayRequests();
    Http::fake([
        COMPOSIO.'/files/upload/request' => Http::response(['key' => 'uploads/abc.jpg', 'new_presigned_url' => 'https://storage.composio.test/abc', 'metadata' => ['storage_backend' => 's3']]),
        'storage.composio.test/*' => Http::response(),
        COMPOSIO.'/tools/execute/INSTAGRAM_POST_IG_USER_MEDIA_PUBLISH' => Http::response(['successful' => true, 'data' => ['id' => 'media-1']]),
        COMPOSIO.'/tools/execute/INSTAGRAM_POST_IG_USER_MEDIA' => Http::response(['successful' => true, 'data' => ['id' => 'container-1']]),
        COMPOSIO.'/tools/execute/INSTAGRAM_GET_IG_MEDIA' => Http::response(['successful' => true, 'data' => ['permalink' => 'https://www.instagram.com/p/abc/']]),
    ]);
}

it('keeps the organization\'s Composio key encrypted and never sends it back', function () {
    [$user, $project] = instagramSetup();

    $this->actingAs($user)
        ->put(route('settings.composio.update'), ['api_key' => 'ak_composio-secret'])
        ->assertRedirect(route('settings.composio.edit'));

    expect($project->organization->fresh()->composio_api_key)->toBe('ak_composio-secret')
        ->and(DB::table('organizations')->where('id', $project->organization_id)->value('composio_api_key'))->not->toContain('composio-secret');

    $this->get(route('settings.composio.edit'))
        ->assertOk()
        ->assertDontSee('composio-secret')
        ->assertInertia(fn ($page) => $page->where('configured', true)->missing('apiKey'));
});

it('disconnects, with the old key, what a new key cannot reach', function () {
    [$user, $project] = instagramSetup();
    $account = SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id]);
    $account->integration->update(['connected_account_id' => 'ca_old']);
    Http::preventStrayRequests();
    Http::fake([COMPOSIO.'/connected_accounts/ca_old' => Http::response()]);

    $this->actingAs($user)->put(route('settings.composio.update'), ['api_key' => 'ak_another-key']);

    expect(Integration::count())->toBe(0)
        ->and($account->fresh())->toBeNull()
        ->and($project->organization->fresh()->composio_api_key)->toBe('ak_another-key');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request->header('x-api-key') === ['ak_composio-test-key']);
});

it('keeps every connection when the same key is saved again', function () {
    [$user, $project] = instagramSetup();
    SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id]);
    Http::preventStrayRequests();

    $this->actingAs($user)->put(route('settings.composio.update'), ['api_key' => 'ak_composio-test-key']);

    expect(Integration::count())->toBe(1);
});

it('cannot reach another organization\'s connections through the key screen', function () {
    [$user] = instagramSetup();
    $foreign = SocialAccount::factory()->instagram()->create();
    Http::preventStrayRequests();

    $this->actingAs($user)->delete(route('settings.composio.destroy'));

    expect($foreign->fresh())->not->toBeNull();
});

it('connects an Instagram account through Composio and grants it to this project', function () {
    [$user, $project] = instagramSetup();
    Http::preventStrayRequests();
    Http::fake([
        COMPOSIO.'/auth_configs' => Http::response(['auth_config' => ['id' => 'ac_1']], 201),
        COMPOSIO.'/connected_accounts/link' => Http::response(['redirect_url' => 'https://connect.composio.test/link/abc', 'connected_account_id' => 'ca_new'], 201),
        COMPOSIO.'/connected_accounts/ca_new' => Http::response(['status' => 'ACTIVE']),
        COMPOSIO.'/tools/execute/INSTAGRAM_GET_USER_INFO' => Http::response(['successful' => true, 'data' => ['id' => '17841', 'username' => 'acme', 'name' => 'Acme']]),
    ]);

    $this->actingAs($user)->get(route('settings.integrations.connect', 'instagram'))
        ->assertRedirect('https://connect.composio.test/link/abc');

    $this->get(route('oauth.composio.callback'))
        ->assertRedirect(route('settings.instagram.index', ['project' => $project]));

    expect(SocialAccount::sole())
        ->platform->toBe(SocialPlatform::Instagram)
        ->external_id->toBe('17841')
        ->handle->toBe('acme')
        ->secret->toBeNull()
        ->and(SocialAccount::sole()->integration)
        ->connected_account_id->toBe('ca_new')
        ->status->toBe(IntegrationStatus::Active)
        ->and(SocialAccount::sole()->projects->pluck('id')->all())->toBe([$project->id]);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/connected_accounts/link')
        && $request['auth_config_id'] === 'ac_1'
        && $request['callback_url'] === route('oauth.composio.callback'));
});

it('stores nothing when the authorization was not completed', function () {
    [$user, $project] = instagramSetup();
    Http::preventStrayRequests();
    Http::fake([
        COMPOSIO.'/auth_configs' => Http::response(['auth_config' => ['id' => 'ac_1']], 201),
        COMPOSIO.'/connected_accounts/link' => Http::response(['redirect_url' => 'https://connect.composio.test/link/abc', 'connected_account_id' => 'ca_new'], 201),
        COMPOSIO.'/connected_accounts/ca_new' => Http::response(['status' => 'FAILED']),
    ]);

    $this->actingAs($user)->get(route('settings.integrations.connect', 'instagram'));

    $this->get(route('oauth.composio.callback'))
        ->assertRedirect(route('settings.instagram.index', ['project' => $project]));

    expect(SocialAccount::count())->toBe(0)
        ->and(Integration::count())->toBe(0);
});

it('ignores a callback this session never started', function () {
    [$user] = instagramSetup();
    Http::preventStrayRequests();

    $this->actingAs($user)->get(route('oauth.composio.callback'))->assertRedirect(route('app.home'));

    expect(SocialAccount::count())->toBe(0);
});

it('draws a JPEG for an Instagram draft from the writer\'s brief, and meters it', function () {
    Notification::fake();
    Storage::fake();
    $project = Project::factory()->create();
    fakeInstagramWriter(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A caption. #widgets', 'image_brief' => 'A workbench at dawn.']);
    Image::fake([instagramPng()]);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Instagram);

    $post = SocialPost::sole();

    expect($post->image_brief)->toBe('A workbench at dawn.')
        ->and($post->image->getCustomProperty('brief'))->toBe('A workbench at dawn.')
        ->and(substr((string) stream_get_contents($post->image->stream()), 0, 2))->toBe("\xFF\xD8")
        ->and(AgentRun::query()->where('agent', ImageGenerator::slug())->sole()->status)->toBe(AgentRunStatus::Succeeded);

    Image::assertGenerated(fn (ImagePrompt $prompt): bool => $prompt->contains('A workbench at dawn.'));
});

it('keeps the draft with the reason when the image cannot be drawn, and never publishes it on its own', function () {
    Notification::fake();
    Storage::fake();
    Http::preventStrayRequests();
    $project = Project::factory()->create(['instagram_autonomy_level' => AutonomyLevel::Autonomous]);
    SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id])->projects()->attach($project);
    fakeInstagramWriter(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A caption.', 'image_brief' => 'A brief.']);
    Image::fake([base64_encode('not an image')]);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Instagram);

    expect(SocialPost::sole())
        ->status->toBe(SocialPostStatus::Draft)
        ->image_media_id->toBeNull()
        ->image_brief->toBe('A brief.')
        ->last_error->toContain('The image could not be generated');
});

it('publishes an approved Instagram draft with the picked image as a file', function () {
    [$user, $project] = instagramSetup();
    Storage::fake();
    $account = SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id, 'external_id' => '17841']);
    $account->projects()->attach($project);
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id, 'body' => 'A caption.']);
    instagramImage($post, 'other-bytes', 'other.jpg');
    $post->update(['image_media_id' => instagramImage($post)->id]);
    fakeComposioPublishing();

    $this->actingAs($user)->post(route('social.posts.approve', $post), ['social_account_id' => $account->id]);

    expect($post->fresh())
        ->status->toBe(SocialPostStatus::Published)
        ->external_id->toBe('media-1')
        ->url->toBe('https://www.instagram.com/p/abc/');

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://storage.composio.test/') && $request->body() === 'jpeg-bytes');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/INSTAGRAM_POST_IG_USER_MEDIA')
        && $request['connected_account_id'] === $account->integration->connected_account_id
        && $request['arguments'] === [
            'ig_user_id' => '17841',
            'caption' => 'A caption.',
            'image_file' => ['name' => 'a.jpg', 'mimetype' => 'image/jpeg', 's3key' => 'uploads/abc.jpg'],
        ]);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/INSTAGRAM_POST_IG_USER_MEDIA_PUBLISH')
        && $request['arguments']['creation_id'] === 'container-1');
});

it('draws the missing image when a draft is approved, so approving again is the retry', function () {
    [$user, $project] = instagramSetup();
    Storage::fake();
    $account = SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id]);
    $account->projects()->attach($project);
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id, 'body' => 'A caption.', 'last_error' => 'The image could not be generated: timeout']);
    Image::fake([instagramPng()]);
    fakeComposioPublishing();

    $this->actingAs($user)->post(route('social.posts.approve', $post), ['social_account_id' => $account->id]);

    expect($post->fresh())
        ->status->toBe(SocialPostStatus::Published)
        ->image_media_id->toBe(Media::sole()->id);
});

it('serves a post\'s images to its own project only', function () {
    [$user, $project] = instagramSetup();
    Storage::fake();
    $ownPost = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    $own = instagramImage($ownPost);
    $foreignPost = SocialPost::factory()->instagram()->create();
    $foreign = instagramImage($foreignPost);

    $this->actingAs($user)->get(route('social.posts.images.show', [$ownPost, $own]))->assertOk();
    $this->actingAs($user)->get(route('social.posts.images.show', [$foreignPost, $foreign]))->assertNotFound();
    // Another post's image through one's own post id.
    $this->actingAs($user)->get(route('social.posts.images.show', [$ownPost, $foreign]))->assertNotFound();
});

it('queues another image for a draft, with what the user wants changed', function () {
    [$user, $project] = instagramSetup();
    Queue::fake([GenerateSocialPostImage::class]);
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('social.posts.images.store', $post), ['note' => 'Brighter.']);

    Queue::assertPushed(GenerateSocialPostImage::class, fn (GenerateSocialPostImage $job): bool => $job->post->is($post) && $job->note === 'Brighter.');
});

it('refuses another image for a published post or a network without images', function () {
    [$user, $project] = instagramSetup();
    Queue::fake([GenerateSocialPostImage::class]);
    $published = SocialPost::factory()->instagram()->create(['project_id' => $project->id, 'status' => SocialPostStatus::Published]);
    $bluesky = SocialPost::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('social.posts.images.store', $published))->assertNotFound();
    $this->actingAs($user)->post(route('social.posts.images.store', $bluesky))->assertNotFound();

    Queue::assertNothingPushed();
});

it('keeps every image and the one picked when another is drawn', function () {
    [, $project] = instagramSetup();
    Storage::fake();
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id, 'image_brief' => 'A workbench at dawn.']);
    $first = instagramImage($post);
    $post->update(['image_media_id' => $first->id]);
    Image::fake([instagramPng()]);

    GenerateSocialPostImage::dispatchSync($post, 'Brighter.');

    expect($post->fresh()->image_media_id)->toBe($first->id)
        ->and($post->fresh()->getMedia('images'))->toHaveCount(2);

    Image::assertGenerated(fn (ImagePrompt $prompt): bool => $prompt->contains('A workbench at dawn.') && $prompt->contains('Brighter.'));
});

it('picks which image a draft is published with, among its own only', function () {
    [$user, $project] = instagramSetup();
    Storage::fake();
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    $first = instagramImage($post);
    $second = instagramImage($post, 'b', 'b.jpg');
    $post->update(['image_media_id' => $first->id]);
    $foreign = instagramImage(SocialPost::factory()->instagram()->create());

    $this->actingAs($user)->put(route('social.posts.images.update', [$post, $second]));
    $this->actingAs($user)->put(route('social.posts.images.update', [$post, $foreign]))->assertNotFound();

    expect($post->fresh()->image_media_id)->toBe($second->id);
});

it('revokes the Composio connection when an Instagram account is disconnected', function () {
    [$user, $project] = instagramSetup();
    $account = SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id]);
    $account->integration->update(['connected_account_id' => 'ca_old']);
    Http::preventStrayRequests();
    Http::fake([COMPOSIO.'/connected_accounts/ca_old' => Http::response()]);

    $this->actingAs($user)->delete(route('settings.social-accounts.destroy', $account));

    expect($account->fresh())->toBeNull()
        ->and(Integration::count())->toBe(0);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/connected_accounts/ca_old'));
});

it('deletes a draft\'s image files along with the draft', function () {
    [$user, $project] = instagramSetup();
    Storage::fake();
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    $path = instagramImage($post)->getPathRelativeToRoot();

    $this->actingAs($user)->delete(route('social.posts.destroy', $post));

    expect(Media::count())->toBe(0);
    Storage::assertMissing($path);
});

it('moves a reconnected Instagram account onto its new connection and revokes the old one', function () {
    [$user, $project] = instagramSetup();
    $account = SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id, 'external_id' => '17841']);
    $account->integration->update(['connected_account_id' => 'ca_old']);
    Http::preventStrayRequests();
    Http::fake([
        COMPOSIO.'/auth_configs' => Http::response(['auth_config' => ['id' => 'ac_1']], 201),
        COMPOSIO.'/connected_accounts/link' => Http::response(['redirect_url' => 'https://connect.composio.test/link/abc', 'connected_account_id' => 'ca_new'], 201),
        COMPOSIO.'/connected_accounts/ca_new' => Http::response(['status' => 'ACTIVE']),
        COMPOSIO.'/connected_accounts/ca_old' => Http::response(),
        COMPOSIO.'/tools/execute/INSTAGRAM_GET_USER_INFO' => Http::response(['successful' => true, 'data' => ['id' => '17841', 'username' => 'acme', 'name' => 'Acme']]),
    ]);

    $this->actingAs($user)->get(route('settings.integrations.connect', 'instagram'));
    $this->get(route('oauth.composio.callback'));

    expect(SocialAccount::sole()->is($account))->toBeTrue()
        ->and(SocialAccount::sole()->integration->connected_account_id)->toBe('ca_new')
        ->and(Integration::count())->toBe(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/connected_accounts/ca_old'));
});

const GRAPH = 'graph.instagram.com';

/**
 * An organization's own Meta app for Instagram.
 */
function instagramMetaApp(Project $project): PlatformApp
{
    return PlatformApp::factory()->create([
        'organization_id' => $project->organization_id,
        'platform' => PlatformAppKind::Instagram,
        'client_id' => 'meta-app-id',
        'client_secret' => 'meta-app-secret',
    ]);
}

/**
 * An Instagram account connected through the organization's own Meta app,
 * and through Composio too unless told otherwise.
 */
function instagramMetaAccount(Project $project, PlatformApp $app, bool $composio = true, ?CarbonInterface $expiresAt = null): SocialAccount
{
    $account = SocialAccount::factory()->instagram()->create([
        'organization_id' => $project->organization_id,
        'external_id' => '17841',
        'platform_app_id' => $app->id,
        'secret' => 'meta-token',
        'secret_expires_at' => $expiresAt ?? now()->addDays(50),
    ]);
    $account->projects()->attach($project);

    if (! $composio) {
        $account->integration->delete();
    }

    return $account->fresh();
}

it('saves the organization\'s own Meta app, its secret encrypted', function () {
    [$user, $project] = instagramSetup();

    $this->actingAs($user)->put(route('settings.platform-apps.update', 'instagram'), ['client_id' => 'meta-app-id', 'client_secret' => 'meta-app-secret']);

    $app = PlatformApp::sole();

    expect($app->platform)->toBe(PlatformAppKind::Instagram)
        ->and($app->organization_id)->toBe($project->organization_id)
        ->and(DB::table('platform_apps')->value('client_secret'))->not->toContain('meta-app-secret');

    $this->get(route('settings.instagram.index'))
        ->assertDontSee('meta-app-secret')
        ->assertInertia(fn ($page) => $page->where('metaApp.client_id', 'meta-app-id')->where('metaApp.available', true));
});

it('connects through the Meta app on top of Composio, and says the Meta app now takes priority', function () {
    [$user, $project] = instagramSetup();
    $app = instagramMetaApp($project);
    $account = SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id, 'external_id' => '17841']);
    Http::preventStrayRequests();
    Http::fake([
        'api.instagram.com/oauth/access_token' => Http::response(['access_token' => 'short-token', 'user_id' => 17841]),
        GRAPH.'/access_token*' => Http::response(['access_token' => 'long-token', 'expires_in' => 5184000]),
        GRAPH.'/v23.0/me*' => Http::response(['user_id' => '17841', 'username' => 'acme', 'name' => 'Acme']),
    ]);

    $redirect = $this->actingAs($user)->get(route('settings.instagram.meta.connect'));
    parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query['client_id'])->toBe('meta-app-id')
        ->and($query['redirect_uri'])->toBe(route('oauth.instagram.callback'));

    $this->get(route('oauth.instagram.callback', ['code' => 'the-code#_', 'state' => $query['state']]))
        ->assertRedirect(route('settings.instagram.index', ['project' => $project]))
        ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'ahead of Composio'));

    expect($account->fresh())
        ->platform_app_id->toBe($app->id)
        ->secret->toBe('long-token')
        ->integration_id->not->toBeNull()
        ->publishesThroughOwnApp()->toBeTrue();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'api.instagram.com') && $request['code'] === 'the-code');
});

it('stores nothing when the Meta callback state does not match', function () {
    [$user, $project] = instagramSetup();
    instagramMetaApp($project);
    Http::preventStrayRequests();

    $this->actingAs($user)->get(route('settings.instagram.meta.connect'));
    $this->get(route('oauth.instagram.callback', ['code' => 'the-code', 'state' => 'forged']));

    expect(SocialAccount::count())->toBe(0);
});

it('publishes through the Meta app ahead of Composio when an account has both', function () {
    [$user, $project] = instagramSetup();
    Storage::fake();
    Storage::disk()->buildTemporaryUrlsUsing(fn (string $path): string => "https://media.test/{$path}");
    Sleep::fake();
    $account = instagramMetaAccount($project, instagramMetaApp($project));
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id, 'body' => 'A caption.']);
    $post->update(['image_media_id' => instagramImage($post)->id]);
    Http::preventStrayRequests();
    Http::fake([
        GRAPH.'/v23.0/17841/media_publish' => Http::response(['id' => 'media-1']),
        GRAPH.'/v23.0/17841/media' => Http::response(['id' => 'container-1']),
        GRAPH.'/v23.0/container-1*' => Http::response(['status_code' => 'FINISHED']),
        GRAPH.'/v23.0/media-1*' => Http::response(['permalink' => 'https://www.instagram.com/p/abc/']),
    ]);

    $this->actingAs($user)->post(route('social.posts.approve', $post), ['social_account_id' => $account->id]);

    expect($post->fresh())
        ->status->toBe(SocialPostStatus::Published)
        ->url->toBe('https://www.instagram.com/p/abc/');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/17841/media')
        && str_starts_with($request['image_url'], 'https://media.test/')
        && $request['access_token'] === 'meta-token');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'composio'));
});

it('falls back on Composio once the Meta app token has expired', function () {
    [$user, $project] = instagramSetup();
    Storage::fake();
    $account = instagramMetaAccount($project, instagramMetaApp($project), expiresAt: now()->subDay());
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    $post->update(['image_media_id' => instagramImage($post)->id]);
    fakeComposioPublishing();

    $this->actingAs($user)->post(route('social.posts.approve', $post), ['social_account_id' => $account->id]);

    expect($post->fresh()->status)->toBe(SocialPostStatus::Published);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'graph.instagram.com'));
});

it('keeps the account on the other way in when one is dropped', function () {
    [$user, $project] = instagramSetup();
    $account = instagramMetaAccount($project, instagramMetaApp($project));
    $account->integration->update(['connected_account_id' => 'ca_old']);
    Http::preventStrayRequests();
    Http::fake([COMPOSIO.'/connected_accounts/ca_old' => Http::response()]);

    $this->actingAs($user)->delete(route('settings.instagram.connections.destroy', [$account, 'composio']));

    expect($account->fresh())
        ->integration_id->toBeNull()
        ->publishesThroughOwnApp()->toBeTrue();

    $this->actingAs($user)->delete(route('settings.instagram.connections.destroy', [$account, 'meta']));

    expect($account->fresh())->toBeNull();
});

it('falls an account back on Composio when the Meta app is removed, and drops one that had only the Meta app', function () {
    [$user, $project] = instagramSetup();
    $app = instagramMetaApp($project);
    $both = instagramMetaAccount($project, $app);
    $metaOnly = SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id, 'platform_app_id' => $app->id, 'secret' => 'token']);
    $metaOnly->integration->delete();

    $this->actingAs($user)->delete(route('settings.platform-apps.destroy', 'instagram'));

    expect(PlatformApp::count())->toBe(0)
        ->and($both->fresh())->secret->toBeNull()->integration_id->not->toBeNull()
        ->and($metaOnly->fresh())->toBeNull();
});

it('keeps Meta app accounts when the Composio key goes', function () {
    [$user, $project] = instagramSetup();
    $account = instagramMetaAccount($project, instagramMetaApp($project));
    $account->integration->update(['connected_account_id' => 'ca_old']);
    Http::preventStrayRequests();
    Http::fake([COMPOSIO.'/connected_accounts/ca_old' => Http::response()]);

    $this->actingAs($user)->delete(route('settings.composio.destroy'));

    expect($account->fresh())
        ->integration_id->toBeNull()
        ->publishesThroughOwnApp()->toBeTrue();
});

it('refreshes Meta app tokens past their first ten days, and leaves fresh ones alone', function () {
    [, $project] = instagramSetup();
    $app = instagramMetaApp($project);
    $aging = instagramMetaAccount($project, $app, expiresAt: now()->addDays(20));
    $fresh = SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id, 'platform_app_id' => $app->id, 'secret' => 'fresh-token', 'secret_expires_at' => now()->addDays(59)]);
    Http::preventStrayRequests();
    Http::fake([GRAPH.'/refresh_access_token*' => Http::response(['access_token' => 'renewed-token', 'expires_in' => 5184000])]);

    $this->artisan('eveil:instagram-refresh-tokens')->assertSuccessful();

    expect($aging->fresh()->secret)->toBe('renewed-token')
        ->and($aging->fresh()->secret_expires_at->isAfter(now()->addDays(59)))->toBeTrue()
        ->and($fresh->fresh()->secret)->toBe('fresh-token');
    Http::assertSentCount(1);
});

it('refuses a Composio Connect key, with where to find the project key', function () {
    [$user, $project] = instagramSetup();

    $this->actingAs($user)->from(route('settings.composio.edit'))
        ->put(route('settings.composio.update'), ['api_key' => 'ck_consumer-key'])
        ->assertSessionHasErrors('api_key');

    expect($project->organization->fresh()->composio_api_key)->toBe('ak_composio-test-key');
});

it('says a post is being written from the click until the job is done', function () {
    [$user, $project] = instagramSetup();
    Queue::fake([GenerateSocialPost::class]);

    $this->actingAs($user)->post(route('social.posts.generate', 'instagram'));

    $this->get(route('social.posts.index', 'instagram'))->assertInertia(fn ($page) => $page->where('writing', true));

    Notification::fake();
    Storage::fake();
    fakeInstagramWriter(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A caption.', 'image_brief' => 'A brief.']);
    Image::fake([instagramPng()]);
    // Run for real: `Queue::fake()` above swallows a `dispatchSync()` too.
    app()->call([new GenerateSocialPost($project, SocialPlatform::Instagram), 'handle']);

    $this->get(route('social.posts.index', 'instagram'))->assertInertia(fn ($page) => $page->where('writing', false)->where('posts.0.drawing_image', false));
});

it('says a new image is being drawn for a draft until it is done', function () {
    [$user, $project] = instagramSetup();
    Queue::fake([GenerateSocialPostImage::class]);
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('social.posts.images.store', $post));

    $this->get(route('social.posts.index', 'instagram'))->assertInertia(fn ($page) => $page->where('posts.0.drawing_image', true));

    Storage::fake();
    Image::fake([instagramPng()]);
    app()->call([new GenerateSocialPostImage($post), 'handle']);

    $this->get(route('social.posts.index', 'instagram'))->assertInertia(fn ($page) => $page->where('posts.0.drawing_image', false));
});

it('stores a post\'s images under its organization, feature, date and media id, and keeps that folder', function () {
    [, $project] = instagramSetup();
    Storage::fake();
    $this->travelTo('2026-10-21 09:00:00');
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);

    $image = instagramImage($post, fileName: 'a.jpg');
    $expected = "{$project->organization_id}/social-posts/2026-10-21/{$image->id}/a.jpg";

    expect($image->getPathRelativeToRoot())->toBe($expected);
    Storage::assertExists($expected);

    // Read back a day later, the folder it was stored in still holds.
    $this->travelTo('2026-10-22 09:00:00');

    expect(Media::find($image->id)->getPathRelativeToRoot())->toBe($expected);
});

it('refuses to approve a draft while its image is being drawn', function () {
    [$user, $project] = instagramSetup();
    $account = SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id]);
    $account->projects()->attach($project);
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    app(SocialPostProgress::class)->startDrawing($post);
    Http::preventStrayRequests();

    $this->actingAs($user)->post(route('social.posts.approve', $post), ['social_account_id' => $account->id])
        ->assertSessionHasErrors('social_post');

    expect($post->fresh()->status)->toBe(SocialPostStatus::Draft);
});

it('never publishes on its own a draft rejected while its image was drawn', function () {
    Notification::fake();
    Storage::fake();
    Http::preventStrayRequests();
    $project = Project::factory()->create(['instagram_autonomy_level' => AutonomyLevel::Autonomous]);
    SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id])->projects()->attach($project);
    fakeInstagramWriter(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A caption.', 'image_brief' => 'A brief.']);
    // The user rejects the draft while the image is being drawn.
    Image::fake(function () {
        SocialPost::query()->withoutGlobalScopes()->update(['status' => SocialPostStatus::Rejected]);

        return instagramPng();
    });

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Instagram);

    expect(SocialPost::sole()->status)->toBe(SocialPostStatus::Rejected);
});

it('lets Evie ask for another image, behind approval, and pick one', function () {
    [, $project] = instagramSetup();
    Queue::fake([GenerateSocialPostImage::class]);
    Storage::fake();
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    $first = instagramImage($post);
    $second = instagramImage($post, 'b', 'b.jpg');
    $post->update(['image_media_id' => $first->id]);

    expect(new RequestSocialPostImage($project))->toBeInstanceOf(Approvable::class);

    (new RequestSocialPostImage($project))->handle(new ToolRequest(['social_post_id' => $post->id, 'note' => 'Brighter.']));
    Queue::assertPushed(GenerateSocialPostImage::class, fn (GenerateSocialPostImage $job): bool => $job->post->is($post) && $job->note === 'Brighter.');

    (new SelectSocialPostImage($project))->handle(new ToolRequest(['social_post_id' => $post->id, 'image_id' => $second->id]));
    expect($post->fresh()->image_media_id)->toBe($second->id);
});

it('keeps Evie away from another project\'s draft images', function () {
    [, $project] = instagramSetup();
    Storage::fake();
    $foreign = SocialPost::factory()->instagram()->create();
    $image = instagramImage($foreign);

    $answer = (string) (new SelectSocialPostImage($project))->handle(new ToolRequest(['social_post_id' => $foreign->id, 'image_id' => $image->id]));

    expect($answer)->toContain('No draft with that id')
        ->and($foreign->fresh()->image_media_id)->toBeNull();
});

it('clears only stale Composio attempts when someone starts a connection', function () {
    [$user, $project] = instagramSetup();
    $teammates = Integration::factory()->create(['organization_id' => $project->organization_id, 'status' => IntegrationStatus::Pending]);
    $stale = Integration::factory()->create(['organization_id' => $project->organization_id, 'status' => IntegrationStatus::Pending, 'connected_account_id' => 'ca_stale', 'created_at' => now()->subHours(2)]);
    Http::preventStrayRequests();
    Http::fake([
        COMPOSIO.'/connected_accounts/ca_stale' => Http::response(),
        COMPOSIO.'/auth_configs' => Http::response(['auth_config' => ['id' => 'ac_1']], 201),
        COMPOSIO.'/connected_accounts/link' => Http::response(['redirect_url' => 'https://connect.composio.test/link/abc', 'connected_account_id' => 'ca_new'], 201),
    ]);

    $this->actingAs($user)->get(route('settings.integrations.connect', 'instagram'));

    expect($teammates->fresh())->not->toBeNull()
        ->and($stale->fresh())->toBeNull();
});
