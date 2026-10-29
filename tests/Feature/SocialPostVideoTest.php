<?php

use App\Actions\DirectSocialPostVideo;
use App\Ai\Agents\SocialPostWriter;
use App\Ai\Agents\VideoDirector;
use App\Ai\Tools\RequestSocialPostVideo;
use App\Enums\AutonomyLevel;
use App\Enums\BrandAssetKind;
use App\Enums\PlatformAppKind;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Jobs\GenerateSocialPost;
use App\Jobs\GenerateSocialPostVideo;
use App\Models\CrawledPage;
use App\Models\Organization;
use App\Models\PlatformApp;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\User;
use App\Services\Discovery\PageFetcher;
use App\Services\Video\BrandStyle;
use App\Services\Video\VideoAudio;
use App\Services\Video\VideoRenderer;
use App\Services\Video\VideoRenderFailed;
use App\Support\CurrentProject;
use App\Support\SocialPostProgress;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Sleep;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Image;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;
use Laravel\Ai\Tools\Request as ToolRequest;
use Mockery\MockInterface;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

// The director reads the project's homepage for its colours and fonts: never
// over the network in a test.
beforeEach(fn () => $this->mock(PageFetcher::class)->shouldReceive('fetch')->andReturnNull()->byDefault());

/**
 * @return array{0: User, 1: Project}
 */
function videoSetup(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['composio_api_key' => 'ak_composio-test-key']);
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();

    app(CurrentProject::class)->set($project);

    return [$user, $project];
}

function videoStructured(array $structured): StructuredTextResponse
{
    return new StructuredTextResponse($structured, '{}', new TextUsage(inputTokens: 10, outputTokens: 5), new Meta('anthropic', 'claude-opus-5'));
}

function videoScript(string $title = 'Hello'): array
{
    return ['composition' => "v.scene(3, (s) => s.title('{$title}'));", 'summary' => "A title saying {$title}."];
}

/**
 * A rendered video, as the renderer hands it over: a file the caller owns.
 */
function renderedVideo(): string
{
    $path = sys_get_temp_dir().'/'.uniqid('video-', true).'.mp4';
    file_put_contents($path, 'mp4-bytes');

    return $path;
}

function screenshotOf(Project $project, string $description = 'The dashboard'): Media
{
    $image = imagecreatetruecolor(40, 20);
    ob_start();
    imagepng($image);

    return $project->addMediaFromString((string) ob_get_clean())
        ->usingFileName('screen.png')
        ->withCustomProperties(['kind' => BrandAssetKind::Screenshot->value, 'description' => $description])
        ->toMediaCollection('brand_assets');
}

function videoOf(SocialPost $post): void
{
    $media = $post->addMediaFromString('mp4-bytes')->usingFileName('v.mp4')->toMediaCollection('videos');
    $post->update(['video_composition' => "v.scene(3, (s) => s.title('Hi'));", 'video_media_id' => $media->id]);
}

it('asks for a video when the writer wants one, on the networks that take one', function (SocialPlatform $platform, bool $queued) {
    Notification::fake();
    Storage::fake();
    Queue::fake([GenerateSocialPostVideo::class]);
    $project = Project::factory()->create();
    SocialPostWriter::fake([videoStructured(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A caption.', 'image_brief' => 'A brief.', 'video_brief' => 'Show the editor in action.'])]);
    Image::fake([base64_encode((string) file_get_contents(base_path('public/apple-touch-icon.png')))]);

    GenerateSocialPost::dispatchSync($project, $platform);

    $post = SocialPost::sole();

    expect($post->video_brief)->toBe($queued ? 'Show the editor in action.' : null);
    $queued
        ? Queue::assertPushed(GenerateSocialPostVideo::class, fn (GenerateSocialPostVideo $job): bool => $job->post->is($post) && ! $job->publishAfter)
        : Queue::assertNotPushed(GenerateSocialPostVideo::class);
})->with([
    'Instagram' => [SocialPlatform::Instagram, true],
    'LinkedIn' => [SocialPlatform::Linkedin, true],
    'X, posted by hand' => [SocialPlatform::X, false],
]);

it('holds an autonomous post until its video is made, then publishes it with the video', function () {
    Notification::fake();
    Storage::fake();
    Queue::fake([GenerateSocialPostVideo::class]);
    Http::preventStrayRequests();
    $project = Project::factory()->create(['instagram_autonomy_level' => AutonomyLevel::Autonomous]);
    SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id])->projects()->attach($project);
    SocialPostWriter::fake([videoStructured(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A caption.', 'image_brief' => 'A brief.', 'video_brief' => 'Show it.'])]);
    Image::fake([base64_encode((string) file_get_contents(base_path('public/apple-touch-icon.png')))]);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Instagram);

    expect(SocialPost::sole()->status)->toBe(SocialPostStatus::Draft);
    Queue::assertPushed(GenerateSocialPostVideo::class, fn (GenerateSocialPostVideo $job): bool => $job->publishAfter);
    Notification::assertNothingSent();
});

it('makes the video from the brief and the project\'s screenshots, and keeps what it did', function () {
    [, $project] = videoSetup();
    Storage::fake();
    $screenshot = screenshotOf($project, 'The editor with a draft open');
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id, 'body' => 'A caption.', 'video_brief' => 'Show the editor.']);
    VideoDirector::fake([videoStructured(videoScript())]);
    $this->mock(VideoRenderer::class, fn (MockInterface $mock) => $mock->shouldReceive('render')
        ->once()
        ->withArgs(fn (string $composition, array $assets): bool => $composition === videoScript()['composition'] && $assets === ["screen-{$screenshot->id}" => $assets["screen-{$screenshot->id}"]] && $assets["screen-{$screenshot->id}"]->is($screenshot))
        ->andReturn(renderedVideo()));

    app(DirectSocialPostVideo::class)->handle($post);

    $post->refresh();

    expect($post->video_composition)->toBe(videoScript()['composition'])
        ->and($post->video->collection_name)->toBe('videos')
        ->and($post->video_revisions)->toHaveCount(1)
        ->and($post->video_revisions[0])->toMatchArray(['feedback' => null, 'at' => null, 'summary' => 'A title saying Hello.']);

    VideoDirector::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Show the editor.')
        && $prompt->contains("'screen-{$screenshot->id}' (40 by 20 px): The editor with a draft open")
        && $prompt->attachments->count() === 1);
});

it('hands a script that does not build back to the director once, with the error', function () {
    [, $project] = videoSetup();
    Storage::fake();
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    VideoDirector::fake([videoStructured(videoScript('Broken')), videoStructured(videoScript('Fixed'))]);
    $this->mock(VideoRenderer::class, fn (MockInterface $mock) => $mock->shouldReceive('render')
        ->twice()
        ->andReturnUsing(fn (string $composition): string => str_contains($composition, 'Broken') ? throw new VideoRenderFailed('ReferenceError: s.titel is not a function') : renderedVideo()));

    app(DirectSocialPostVideo::class)->handle($post);

    expect($post->fresh()->video_composition)->toContain('Fixed');
    VideoDirector::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('ReferenceError: s.titel is not a function') && $prompt->contains('Broken'));
});

it('leaves the draft without a video, saying why, when neither attempt builds', function () {
    [, $project] = videoSetup();
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    VideoDirector::fake([videoStructured(videoScript()), videoStructured(videoScript())]);
    $this->mock(VideoRenderer::class, fn (MockInterface $mock) => $mock->shouldReceive('render')->twice()->andThrow(new VideoRenderFailed('Timed out')));

    GenerateSocialPostVideo::dispatchSync($post);

    expect($post->fresh())
        ->video_media_id->toBeNull()
        ->last_error->toBe('The video could not be made: Timed out')
        ->and(app(SocialPostProgress::class)->isDirecting($post))->toBeFalse();
});

it('revises the video from what the user said, showing the director the moment they paused at', function () {
    [, $project] = videoSetup();
    Storage::fake();
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id, 'video_revisions' => [['feedback' => null, 'at' => null, 'summary' => 'First cut.', 'created_at' => now()->toIso8601String()]]]);
    videoOf($post);
    VideoDirector::fake([videoStructured(videoScript('Slower'))]);
    $this->mock(VideoRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('stills')->once()->withArgs(fn (string $composition, array $assets, array $times): bool => $times === [3.4, 4.0, 4.8])->andReturn(['png-1', 'png-2', 'png-3']);
        $mock->shouldReceive('render')->once()->andReturn(renderedVideo());
    });

    app(DirectSocialPostVideo::class)->handle($post, 'Too fast here.', 4.0);

    expect($post->fresh()->video_revisions)->toHaveCount(2)
        ->and($post->fresh()->video_revisions[1])->toMatchArray(['feedback' => 'Too fast here.', 'at' => 4.0, 'summary' => 'A title saying Slower.']);

    VideoDirector::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Too fast here.')
        && $prompt->contains("s.title('Hi')")
        && $prompt->contains('First cut.')
        && $prompt->attachments->count() === 3);
});

it('queues a new version with the feedback and the second it is about', function () {
    [$user, $project] = videoSetup();
    Queue::fake([GenerateSocialPostVideo::class]);
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('social.posts.video.store', $post), ['feedback' => 'End on the pricing page.', 'at' => 7.25])
        ->assertSessionHasNoErrors();

    Queue::assertPushed(GenerateSocialPostVideo::class, fn (GenerateSocialPostVideo $job): bool => $job->post->is($post) && $job->feedback === 'End on the pricing page.' && $job->at === 7.25);
    expect(app(SocialPostProgress::class)->isDirecting($post))->toBeTrue();
});

it('refuses a new version while one is being made', function () {
    [$user, $project] = videoSetup();
    Queue::fake([GenerateSocialPostVideo::class]);
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    app(SocialPostProgress::class)->startDirecting($post);

    $this->actingAs($user)->post(route('social.posts.video.store', $post), ['feedback' => 'Again.'])
        ->assertSessionHasErrors('feedback');

    Queue::assertNothingPushed();
});

it('keeps the video editor to this project\'s drafts on networks that take a video', function () {
    [$user, $project] = videoSetup();
    Queue::fake([GenerateSocialPostVideo::class]);
    $foreign = SocialPost::factory()->instagram()->create();
    $x = SocialPost::factory()->x()->create(['project_id' => $project->id]);
    $published = SocialPost::factory()->instagram()->create(['project_id' => $project->id, 'status' => SocialPostStatus::Published]);

    $this->actingAs($user)->get(route('social.posts.video.show', $foreign))->assertNotFound();
    $this->actingAs($user)->get(route('social.posts.video.show', $x))->assertNotFound();
    $this->actingAs($user)->post(route('social.posts.video.store', $foreign))->assertNotFound();
    $this->actingAs($user)->post(route('social.posts.video.store', $published))->assertNotFound();
    $this->actingAs($user)->get(route('social.posts.video.preview', $foreign))->assertNotFound();
    $this->actingAs($user)->get(route('social.posts.video.file', $foreign))->assertNotFound();

    Queue::assertNothingPushed();
});

it('opens the editor on the draft with its versions', function () {
    [$user, $project] = videoSetup();
    Storage::fake();
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id, 'video_revisions' => [['feedback' => null, 'at' => null, 'summary' => 'First cut.', 'created_at' => now()->toIso8601String()]]]);
    videoOf($post);

    $this->actingAs($user)->get(route('social.posts.video.show', $post))
        ->assertInertia(fn ($page) => $page->component('social/VideoEditor')
            ->where('post.id', $post->id)
            ->where('post.video_revisions.0.summary', 'First cut.')
            ->where('post.video_preview_url', fn (string $url): bool => str_contains($url, "/posts/{$post->id}/video/preview")));
});

it('serves the preview sandboxed, with no network, and the images through signed links only', function () {
    [$user, $project] = videoSetup();
    Storage::fake();
    $screenshot = screenshotOf($project);
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    videoOf($post);

    $response = $this->actingAs($user)->get(route('social.posts.video.preview', $post))->assertOk();

    expect($response->headers->get('Content-Security-Policy'))->toContain("connect-src 'none'")
        ->and($response->getContent())->toContain("s.title('Hi')")
        ->and($response->getContent())->toContain('signature=');

    // Signed, it serves the image without a session; unsigned, never.
    auth()->logout();
    $this->get(URL::temporarySignedRoute('video.assets.show', now()->addHour(), ['media' => $screenshot->id]))->assertOk();
    $this->get(route('video.assets.show', ['media' => $screenshot->id]))->assertForbidden();
});

it('serves the runtime files to the preview, and nothing else', function () {
    $this->get(route('video.runtime.show', ['file' => 'runtime.js']))->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=utf-8');
    $this->get(route('video.runtime.show', ['file' => 'fonts/Sora.ttf']))->assertOk();
    $this->get('/app/video/runtime/render.mjs')->assertNotFound();
    $this->get('/app/video/runtime/page.html')->assertNotFound();
});

it('keeps a closing script tag in a composition from ending the page\'s script', function () {
    $page = app(VideoRenderer::class)->page("v.scene(2, (s) => s.title('</script><img src=x>'));", [], 'https://app.test/');

    expect($page)->not->toContain("'</script>")
        ->and($page)->toContain("'<\\/script><img src=x>'");
});

it('refuses to approve a draft while its video is being made', function () {
    [$user, $project] = videoSetup();
    $account = SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id]);
    $account->projects()->attach($project);
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    app(SocialPostProgress::class)->startDirecting($post);
    Http::preventStrayRequests();

    $this->actingAs($user)->post(route('social.posts.approve', $post), ['social_account_id' => $account->id])
        ->assertSessionHasErrors('social_post');

    expect($post->fresh()->status)->toBe(SocialPostStatus::Draft);
});

it('publishes a draft with a video as a Reel through Composio', function () {
    [$user, $project] = videoSetup();
    Storage::fake();
    $account = SocialAccount::factory()->instagram()->create(['organization_id' => $project->organization_id, 'external_id' => '17841']);
    $account->projects()->attach($project);
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id, 'body' => 'A caption.']);
    $post->update(['image_media_id' => $post->addMediaFromString('jpeg')->usingFileName('a.jpg')->toMediaCollection('images')->id]);
    videoOf($post);
    Http::preventStrayRequests();
    Http::fake([
        'backend.composio.dev/api/v3.1/files/upload/request' => Http::response(['key' => 'uploads/v.mp4', 'new_presigned_url' => 'https://storage.composio.test/v', 'metadata' => ['storage_backend' => 's3']]),
        'storage.composio.test/*' => Http::response(),
        'backend.composio.dev/api/v3.1/tools/execute/INSTAGRAM_POST_IG_USER_MEDIA_PUBLISH' => Http::response(['successful' => true, 'data' => ['id' => 'media-1']]),
        'backend.composio.dev/api/v3.1/tools/execute/INSTAGRAM_POST_IG_USER_MEDIA' => Http::response(['successful' => true, 'data' => ['id' => 'container-1']]),
        'backend.composio.dev/api/v3.1/tools/execute/INSTAGRAM_GET_IG_MEDIA' => Http::response(['successful' => true, 'data' => ['permalink' => 'https://www.instagram.com/reel/abc/']]),
    ]);

    $this->actingAs($user)->post(route('social.posts.approve', $post), ['social_account_id' => $account->id]);

    expect($post->fresh()->status)->toBe(SocialPostStatus::Published);
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://storage.composio.test/') && $request->body() === 'mp4-bytes');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/INSTAGRAM_POST_IG_USER_MEDIA')
        && $request['arguments']['media_type'] === 'REELS'
        && $request['arguments']['video_file']['mimetype'] === 'video/mp4'
        && ! isset($request['arguments']['image_file']));
});

it('publishes a draft with a video as a Reel through the Meta app', function () {
    [$user, $project] = videoSetup();
    Storage::fake();
    Storage::disk()->buildTemporaryUrlsUsing(fn (string $path): string => "https://media.test/{$path}");
    Sleep::fake();
    $account = SocialAccount::factory()->instagram()->create([
        'organization_id' => $project->organization_id,
        'external_id' => '17841',
        'platform_app_id' => PlatformApp::factory()->create(['organization_id' => $project->organization_id, 'platform' => PlatformAppKind::Instagram])->id,
        'secret' => 'meta-token',
        'secret_expires_at' => now()->addDays(50),
    ]);
    $account->projects()->attach($project);
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id, 'body' => 'A caption.']);
    videoOf($post);
    Http::preventStrayRequests();
    Http::fake([
        'graph.instagram.com/v23.0/17841/media_publish' => Http::response(['id' => 'media-1']),
        'graph.instagram.com/v23.0/17841/media' => Http::response(['id' => 'container-1']),
        'graph.instagram.com/v23.0/container-1*' => Http::sequence()->push(['status_code' => 'IN_PROGRESS'])->push(['status_code' => 'FINISHED']),
        'graph.instagram.com/v23.0/media-1*' => Http::response(['permalink' => 'https://www.instagram.com/reel/abc/']),
    ]);

    $this->actingAs($user)->post(route('social.posts.approve', $post), ['social_account_id' => $account->id]);

    expect($post->fresh())->status->toBe(SocialPostStatus::Published)->url->toBe('https://www.instagram.com/reel/abc/');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/17841/media')
        && $request['media_type'] === 'REELS'
        && str_starts_with($request['video_url'], 'https://media.test/'));
});

it('lets Evie ask for a new version of a video, behind approval, on this project\'s drafts only', function () {
    [, $project] = videoSetup();
    Queue::fake([GenerateSocialPostVideo::class]);
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    $foreign = SocialPost::factory()->instagram()->create();

    expect(new RequestSocialPostVideo($project))->toBeInstanceOf(Approvable::class);

    (new RequestSocialPostVideo($project))->handle(new ToolRequest(['social_post_id' => $post->id, 'feedback' => 'The intro is too fast.', 'at' => 1.5]));
    Queue::assertPushed(GenerateSocialPostVideo::class, fn (GenerateSocialPostVideo $job): bool => $job->post->is($post) && $job->feedback === 'The intro is too fast.' && $job->at === 1.5);

    $answer = (string) (new RequestSocialPostVideo($project))->handle(new ToolRequest(['social_post_id' => $foreign->id]));
    expect($answer)->toContain('draft with that id exists on this project');
    Queue::assertPushed(GenerateSocialPostVideo::class, 1);
});

it('films a real composition to an MP4 and to stills', function () {
    $video = app(VideoRenderer::class)->render("v.scene(1, (s) => { s.title('Hello **there**'); });", []);
    $stills = app(VideoRenderer::class)->stills("v.scene(1, (s) => { s.title('Hello'); });", [], [0.5]);

    expect(filesize($video))->toBeGreaterThan(1000)
        ->and(substr($stills[0], 1, 3))->toBe('PNG');

    unlink($video);
})->skip(fn (): bool => ! is_executable((string) exec('command -v '.escapeshellarg((string) config('eveil.video.chrome')))) || ! is_executable((string) exec('command -v ffmpeg')), 'Chromium and ffmpeg are not installed here.');

it('gives the video a voice and music when the instance has a key, reuses them, and drops what a new version no longer says', function () {
    [, $project] = videoSetup();
    Storage::fake();
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    VideoDirector::fake([videoStructured(videoScript('One')), videoStructured(videoScript('Two'))]);
    $this->mock(VideoAudio::class, function (MockInterface $mock): void {
        $mock->shouldReceive('available')->andReturnTrue();
        $mock->shouldReceive('speak')->with('Meet Cargo.', 'Brian')->once()->andReturn(str_repeat('v', 32000));
        $mock->shouldReceive('speak')->with('Try it free.', 'Brian')->once()->andReturn('voice-2');
        $mock->shouldReceive('compose')->with('warm lo-fi', 12.0)->once()->andReturn('music');
    });
    $this->mock(VideoRenderer::class, function (MockInterface $mock): void {
        // Each version asks twice: what to voice, then the length once voiced.
        $mock->shouldReceive('manifest')->times(4)->andReturn(
            ['duration' => 9.0, 'voices' => ['Meet Cargo.', 'Try it free.'], 'narrator' => 'Brian', 'music' => 'warm lo-fi'],
            ['duration' => 12.0, 'voices' => ['Meet Cargo.', 'Try it free.'], 'narrator' => 'Brian', 'music' => 'warm lo-fi'],
            ['duration' => 8.0, 'voices' => ['Meet Cargo.'], 'narrator' => 'Brian', 'music' => 'warm lo-fi'],
            ['duration' => 10.0, 'voices' => ['Meet Cargo.'], 'narrator' => 'Brian', 'music' => 'warm lo-fi'],
        );
        $mock->shouldReceive('render')->twice()
            ->withArgs(fn (string $composition, array $assets, array $audio): bool => isset($audio['voices']['Meet Cargo.'], $audio['music']))
            ->andReturnUsing(fn (): string => renderedVideo());
    });

    app(DirectSocialPostVideo::class)->handle($post);
    app(DirectSocialPostVideo::class)->handle($post->fresh(), 'Shorter.');

    // The second version kept one line and the music, made nothing new, and
    // dropped the line it no longer says.
    expect($post->fresh()->getMedia('video_audio')->map(fn (Media $media): ?string => $media->getCustomProperty('voice') ?? $media->getCustomProperty('music'))->sort()->values()->all())
        ->toBe(['Meet Cargo.', 'warm lo-fi']);
    VideoDirector::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains((string) $prompt->agent->instructions(), 's.voice(text'));
});

it('tells the director there is no voice when the instance has no key', function () {
    [, $project] = videoSetup();
    Storage::fake();
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    VideoDirector::fake([videoStructured(videoScript())]);
    $this->mock(VideoRenderer::class, fn (MockInterface $mock) => $mock->shouldReceive('render')->once()->andReturn(renderedVideo()));

    app(DirectSocialPostVideo::class)->handle($post);

    VideoDirector::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains((string) $prompt->agent->instructions(), 'This instance has no voice and no music'));
});

it('publishes a LinkedIn draft with its video, uploaded in parts and processed first', function () {
    [$user, $project] = videoSetup();
    Storage::fake();
    Sleep::fake();
    $account = SocialAccount::factory()->linkedin()->create(['organization_id' => $project->organization_id, 'external_id' => 'urn:li:person:abc']);
    $account->projects()->attach($project);
    $post = SocialPost::factory()->linkedin()->create(['project_id' => $project->id, 'body' => 'A post.']);
    videoOf($post);
    Http::preventStrayRequests();
    Http::fake([
        'api.linkedin.com/rest/videos?action=initializeUpload' => Http::response(['value' => [
            'video' => 'urn:li:video:v1',
            'uploadToken' => 'token-1',
            'uploadInstructions' => [
                ['uploadUrl' => 'https://upload.linkedin.test/part-1', 'firstByte' => 0, 'lastByte' => 3],
                ['uploadUrl' => 'https://upload.linkedin.test/part-2', 'firstByte' => 4, 'lastByte' => 8],
            ],
        ]]),
        'upload.linkedin.test/part-1' => Http::response('', 200, ['ETag' => '"etag-1"']),
        'upload.linkedin.test/part-2' => Http::response('', 200, ['ETag' => '"etag-2"']),
        'api.linkedin.com/rest/videos?action=finalizeUpload' => Http::response(),
        'api.linkedin.com/rest/videos/*' => Http::sequence()->push(['status' => 'PROCESSING'])->push(['status' => 'AVAILABLE']),
        'api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:1']),
    ]);

    $this->actingAs($user)->post(route('social.posts.approve', $post), ['social_account_id' => $account->id]);

    expect($post->fresh()->status)->toBe(SocialPostStatus::Published);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://upload.linkedin.test/part-1' && $request->body() === 'mp4-');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://upload.linkedin.test/part-2' && $request->body() === 'bytes');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'finalizeUpload') && $request['finalizeUploadRequest']['uploadedPartIds'] === ['etag-1', 'etag-2']);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/rest/posts') && $request['content'] === ['media' => ['id' => 'urn:li:video:v1']]);
});

it('publishes a Bluesky draft with its video, through the video service, instead of a link card', function () {
    [$user, $project] = videoSetup();
    Storage::fake();
    Sleep::fake();
    $account = SocialAccount::factory()->create(['organization_id' => $project->organization_id]);
    $account->projects()->attach($project);
    $post = SocialPost::factory()->create(['project_id' => $project->id, 'body' => 'A post https://cargo.example']);
    videoOf($post);
    Http::preventStrayRequests();
    Http::fake([
        'bsky.social/xrpc/com.atproto.server.createSession' => Http::response(['accessJwt' => 'jwt', 'did' => 'did:plc:acme', 'handle' => 'acme.bsky.social', 'didDoc' => ['service' => [['id' => '#atproto_pds', 'serviceEndpoint' => 'https://morel.us-east.host.bsky.network']]]]),
        'bsky.social/xrpc/com.atproto.server.getServiceAuth*' => Http::response(['token' => 'service-token']),
        'video.bsky.app/xrpc/app.bsky.video.uploadVideo*' => Http::response(['jobId' => 'job-1', 'state' => 'JOB_STATE_CREATED']),
        'video.bsky.app/xrpc/app.bsky.video.getJobStatus*' => Http::sequence()
            ->push(['jobStatus' => ['jobId' => 'job-1', 'state' => 'JOB_STATE_ENCODING']])
            ->push(['jobStatus' => ['jobId' => 'job-1', 'state' => 'JOB_STATE_COMPLETED', 'blob' => ['$type' => 'blob', 'ref' => ['$link' => 'bafy'], 'mimeType' => 'video/mp4', 'size' => 9]]]),
        'bsky.social/xrpc/com.atproto.repo.createRecord' => Http::response(['uri' => 'at://did:plc:acme/app.bsky.feed.post/1']),
    ]);

    $this->actingAs($user)->post(route('social.posts.approve', $post), ['social_account_id' => $account->id]);

    expect($post->fresh()->status)->toBe(SocialPostStatus::Published);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'getServiceAuth') && $request['aud'] === 'did:web:morel.us-east.host.bsky.network');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'uploadVideo') && $request->hasHeader('Authorization', 'Bearer service-token') && $request->body() === 'mp4-bytes');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'createRecord')
        && $request['record']['embed']['$type'] === 'app.bsky.embed.video'
        && $request['record']['embed']['video']['ref']['$link'] === 'bafy');
});

it('lengthens a scene to fit its voice lines, one after the other, so they never talk over each other', function () {
    $script = "v.scene(2, (s) => { s.title('Hi'); s.voice('First line.', { at: 0.2 }); s.voice('Second line.', { at: 0.5 }); }); v.scene(1, (s) => s.title('Bye'));";

    Storage::fake();
    $post = SocialPost::factory()->instagram()->create();
    $line = fn (string $text, float $seconds): Media => $post->addMediaFromString('mp3')->usingFileName('a.mp3')->withCustomProperties(['voice' => $text, 'seconds' => $seconds])->toMediaCollection('video_audio');

    $manifest = app(VideoRenderer::class)->manifest($script, [], [
        'First line.' => $line('First line.', 2.0),
        'Second line.' => $line('Second line.', 1.5),
    ]);

    // 0.2 + 2.0 + 0.15, then 1.5 + 0.15, then 0.35 of air: 4.35 s, plus the 1 s scene.
    expect($manifest['duration'])->toBe(5.35);
})->skip(fn (): bool => ! is_executable((string) exec('command -v '.escapeshellarg((string) config('eveil.video.chrome')))), 'Chromium is not installed here.');

it('dresses the video in the brand: shows the director its logo and style examples, with their colours and the site\'s', function () {
    [, $project] = videoSetup();
    Storage::fake();
    $project->update(['url' => 'https://brand.test']);
    $logo = imagecreatetruecolor(20, 20);
    imagefill($logo, 0, 0, imagecolorallocate($logo, 0xEE, 0x33, 0x11));
    ob_start();
    imagepng($logo);
    $project->addMediaFromString((string) ob_get_clean())->usingFileName('logo.png')->withCustomProperties(['kind' => BrandAssetKind::Logo->value])->toMediaCollection('brand_assets');
    $project->addMediaFromString((string) file_get_contents(base_path('public/apple-touch-icon.png')))->usingFileName('ex.png')->withCustomProperties(['kind' => BrandAssetKind::Example->value])->toMediaCollection('brand_assets');
    $this->mock(PageFetcher::class)->shouldReceive('fetch')->with('https://brand.test')->andReturn(new CrawledPage(['content' => '<meta name="theme-color" content="#0a7"><link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;700">']));
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    VideoDirector::fake([videoStructured(videoScript())]);
    $this->mock(VideoRenderer::class, fn (MockInterface $mock) => $mock->shouldReceive('render')->once()->andReturn(renderedVideo()));

    app(DirectSocialPostVideo::class)->handle($post);

    VideoDirector::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Logo colours, most present first: #ee3311')
        && $prompt->contains('Colours its homepage uses, most used first: #00aa77')
        && $prompt->contains('Fonts its homepage uses: Space Grotesk')
        && $prompt->contains('1 of its own published visuals are attached after the logo')
        && $prompt->attachments->count() === 2);
});

it('reads a homepage\'s colours and fonts, leaving generic ones out', function () {
    $site = app(BrandStyle::class)->site(<<<'HTML'
        <meta name="theme-color" content="#123456">
        <link href="https://fonts.bunny.net/css?family=bricolage-grotesque:400,700" rel="stylesheet">
        <style>body { font-family: 'Fraunces', serif; color: #333; } a { color: #ff6600 } .b { background: #ff6600 }</style>
        <script>const notAColour = '#abcdef';</script>
        HTML);

    expect($site['colors'])->toBe(['#123456', '#ff6600', '#333333'])
        ->and($site['fonts'])->toBe(['Bricolage Grotesque', 'Fraunces']);
});

it('gives a new narrator\'s voice to the same line rather than reusing another narrator\'s', function () {
    [, $project] = videoSetup();
    Storage::fake();
    $post = SocialPost::factory()->instagram()->create(['project_id' => $project->id]);
    $post->addMediaFromString('old')->usingFileName('a.mp3')->withCustomProperties(['voice' => 'Meet Cargo.', 'narrator' => 'Eric', 'seconds' => 1.0])->toMediaCollection('video_audio');
    VideoDirector::fake([videoStructured(videoScript())]);
    $this->mock(VideoAudio::class, function (MockInterface $mock): void {
        $mock->shouldReceive('available')->andReturnTrue();
        $mock->shouldReceive('speak')->with('Meet Cargo.', 'Alice')->once()->andReturn('new');
    });
    $this->mock(VideoRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('manifest')->twice()->andReturn(['duration' => 5.0, 'voices' => ['Meet Cargo.'], 'narrator' => 'Alice', 'music' => null]);
        $mock->shouldReceive('render')->once()->andReturn(renderedVideo());
    });

    app(DirectSocialPostVideo::class)->handle($post);

    expect($post->fresh()->getMedia('video_audio')->sole()->getCustomProperty('narrator'))->toBe('Alice');
});
