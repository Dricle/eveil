<?php

use App\Enums\AdPlatform;
use App\Enums\PlatformAppKind;
use App\Jobs\UploadAdConversion;
use App\Models\AdAccount;
use App\Models\AdConversion;
use App\Models\PlatformApp;
use App\Models\Project;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function conversionToken(Project $project): string
{
    return Str::after($project->organization->createToken('test')->plainTextToken, '|');
}

it('takes a reported goal once and queues its upload', function () {
    Queue::fake();
    $project = Project::factory()->withAdsBudget()->create();
    $token = conversionToken($project);

    $this->withToken($token)
        ->postJson(route('api.conversions.store', $project), ['msclkid' => 'abc123', 'value' => 49.9])
        ->assertAccepted()
        ->assertJsonPath('platform', 'microsoft_ads')
        ->assertJsonPath('duplicate', false);

    $conversion = AdConversion::query()->sole();
    expect($conversion->project_id)->toBe($project->id)
        ->and($conversion->platform)->toBe(AdPlatform::MicrosoftAds)
        ->and($conversion->value_micros)->toBe(49_900_000)
        ->and($conversion->currency)->toBe($project->ads_currency);
    Queue::assertPushed(UploadAdConversion::class, 1);

    // The backend retrying the same call.
    $this->withToken($token)
        ->postJson(route('api.conversions.store', $project), ['msclkid' => 'abc123'])
        ->assertOk()
        ->assertJsonPath('duplicate', true);

    expect(AdConversion::query()->count())->toBe(1);
    Queue::assertPushed(UploadAdConversion::class, 1);
});

it('wants exactly one click id', function (array $payload) {
    $project = Project::factory()->create();

    $this->withToken(conversionToken($project))
        ->postJson(route('api.conversions.store', $project), $payload)
        ->assertUnprocessable();

    expect(AdConversion::query()->count())->toBe(0);
})->with([
    'none' => [['value' => 10]],
    'two' => [['gclid' => 'a', 'fbclid' => 'b']],
]);

it('does not take a goal for a project the token cannot reach', function () {
    $mine = Project::factory()->create();
    $theirs = Project::factory()->create();

    $this->withToken(conversionToken($mine))
        ->postJson(route('api.conversions.store', $theirs), ['gclid' => 'abc'])
        ->assertNotFound();

    expect(AdConversion::query()->count())->toBe(0);
});

it('creates the upload action on Google, uploads the click and starts bidding on conversions', function () {
    $account = AdAccount::factory()->create(['external_id' => '1234567890', 'has_conversion_tracking' => false]);
    $conversion = AdConversion::factory()->for($account->project)->create(['click_id' => 'gclid-1', 'value_micros' => 20_000_000, 'currency' => 'EUR']);

    Http::fake([
        'googleads.googleapis.com/v25/customers/1234567890/googleAds:search' => Http::response(['results' => []]),
        'googleads.googleapis.com/v25/customers/1234567890/conversionActions:mutate' => Http::response(['results' => [['resourceName' => 'customers/1234567890/conversionActions/77']]]),
        'googleads.googleapis.com/v25/customers/1234567890:uploadClickConversions' => Http::response(['results' => [['gclid' => 'gclid-1']]]),
    ]);

    UploadAdConversion::dispatchSync($conversion);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'conversionActions:mutate')
        && $request['operations'][0]['create']['type'] === 'UPLOAD_CLICKS');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), ':uploadClickConversions')
        && $request['conversions'][0] === [
            'gclid' => 'gclid-1',
            'conversionAction' => 'customers/1234567890/conversionActions/77',
            'conversionDateTime' => $conversion->occurred_at->format('Y-m-d H:i:sP'),
            'conversionValue' => 20,
            'currencyCode' => 'EUR',
        ]);
    expect($conversion->fresh()->uploaded_at)->not->toBeNull()
        ->and($account->fresh()->has_conversion_tracking)->toBeTrue();
});

it('keeps the refusal and tries a Google goal again later', function () {
    $account = AdAccount::factory()->create(['external_id' => '1234567890']);
    $conversion = AdConversion::factory()->for($account->project)->create();

    Http::fake([
        'googleads.googleapis.com/v25/customers/1234567890/googleAds:search' => Http::response(['results' => [['conversionAction' => ['resourceName' => 'customers/1234567890/conversionActions/77']]]]),
        'googleads.googleapis.com/v25/customers/1234567890:uploadClickConversions' => Http::response(['partialFailureError' => ['message' => 'The click is too old.']]),
    ]);

    $job = (new UploadAdConversion($conversion))->withFakeQueueInteractions();
    $job->handle();

    $job->assertReleased(600);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), 'conversionActions:mutate'));
    expect($conversion->fresh())
        ->uploaded_at->toBeNull()
        ->last_error->toBe('Google Ads: The click is too old.');
});

it('says so when no account of that network is connected', function () {
    $conversion = AdConversion::factory()->create(['platform' => AdPlatform::MetaAds]);
    Http::fake();

    UploadAdConversion::dispatchSync($conversion);

    Http::assertNothingSent();
    expect($conversion->fresh()->last_error)->toBe('No Meta Ads account is connected to report it to.');
});

it('creates the offline goal on Microsoft and applies the click to it', function () {
    $app = PlatformApp::factory()->create(['platform' => PlatformAppKind::MicrosoftAds]);
    $account = AdAccount::factory()->create(['platform' => AdPlatform::MicrosoftAds, 'platform_app_id' => $app->id, 'external_ref' => ['customer_id' => '9']]);
    $conversion = AdConversion::factory()->for($account->project)->create(['platform' => AdPlatform::MicrosoftAds, 'click_id' => 'ms-1']);

    Http::fake([
        '*/ConversionGoals/QueryByIds' => Http::response(['ConversionGoals' => [['Name' => 'Something else']]]),
        '*/ConversionGoals' => Http::response(['ConversionGoalIds' => [5], 'PartialErrors' => null]),
        '*/OfflineConversions/Apply' => Http::response(['PartialErrors' => null]),
    ]);

    UploadAdConversion::dispatchSync($conversion);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/ConversionGoals')
        && $request['ConversionGoals'][0]['Type'] === 'OfflineConversion');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/OfflineConversions/Apply')
        && $request['OfflineConversions'][0]['MicrosoftClickId'] === 'ms-1'
        && $request['OfflineConversions'][0]['ConversionName'] === 'Eveil goal');
    expect($conversion->fresh()->uploaded_at)->not->toBeNull();
});

it('sends a Meta goal to the account pixel as a server event', function () {
    $app = PlatformApp::factory()->create(['platform' => PlatformAppKind::MetaAds]);
    $account = AdAccount::factory()->create(['platform' => AdPlatform::MetaAds, 'platform_app_id' => $app->id, 'external_id' => '149']);
    $conversion = AdConversion::factory()->for($account->project)->create(['platform' => AdPlatform::MetaAds, 'click_id' => 'fb-1']);

    Http::fake([
        'graph.facebook.com/v23.0/act_149/adspixels*' => Http::response(['data' => [['id' => '888']]]),
        'graph.facebook.com/v23.0/888/events' => Http::response(['events_received' => 1]),
    ]);

    UploadAdConversion::dispatchSync($conversion);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/888/events')
        && $request['data'][0]['event_name'] === 'Lead'
        && str_ends_with($request['data'][0]['user_data']['fbc'], '.fb-1'));
    expect($conversion->fresh()->uploaded_at)->not->toBeNull();
});
