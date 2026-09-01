<?php

use App\Ai\Agents\CompanyQualifier;
use App\Ai\Agents\ResultTriage;
use App\Enums\DiscoveryRunOrigin;
use App\Enums\DiscoveryTaskKind;
use App\Enums\DiscoveryTaskStatus;
use App\Models\Company;
use App\Models\CompanyTargetEvaluation;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\KnownHostSeeder;
use Illuminate\Support\Facades\Http;

/**
 * @return array{0: User, 1: Project, 2: TargetProfile}
 */
function submitter(): array
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($user->organizations()->sole())->create();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id, 'name' => 'Friteries wallonnes']);

    return [$user, $project, $profile];
}

function submittedVerdict(int $score = 85): array
{
    return [
        'is_a_prospect' => true,
        'fit_score' => $score,
        'fit_reason' => 'Friterie indépendante trouvée par le client.',
        'company_name' => 'Vraie Friterie',
        'industry' => 'Friterie',
        'size' => '1 établissement',
        'location' => 'Namur',
        'language' => 'fr',
    ];
}

function submittedPage(string $body = 'Notre friterie, commandez par téléphone.'): string
{
    return '<!doctype html><html lang="fr"><head><title>Friterie</title></head><body><p>'.$body.'</p></body></html>';
}

beforeEach(fn () => app(Settings::class)->set('crawl.delay_ms', 0));

it('classifies a submitted company site straight to qualification', function () {
    [$user, , $profile] = submitter();

    ResultTriage::fake([['hosts' => [['host' => 'vraie-friterie.be', 'kind' => 'entity', 'reason' => 'One business.']]]]);
    CompanyQualifier::fake([submittedVerdict()]);

    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://vraie-friterie.be/' => Http::response(submittedPage()),
    ]);

    $response = $this->actingAs($user)->post(route('companies.links.store'), [
        'target_profile' => $profile->id,
        'links' => 'https://vraie-friterie.be',
    ]);

    $run = DiscoveryRun::sole();
    $response->assertRedirect(route('discovery-runs.show', $run));

    $company = Company::sole();

    expect($run->origin)->toBe(DiscoveryRunOrigin::Manual)
        ->and($run->target_profile_id)->toBe($profile->id)
        ->and($company->domain)->toBe('vraie-friterie.be')
        ->and($company->source)->toBe('user-submitted');

    $evaluation = CompanyTargetEvaluation::sole();

    expect($evaluation->company_id)->toBe($company->id)
        ->and($evaluation->target_profile_id)->toBe($profile->id);
});

it('harvests a submitted directory and keeps the directory itself as a candidate too', function () {
    // A directory is also a company (ADR-033): a target profile of "review
    // sites" or "launch platforms" wants the directory host as a lead, not
    // only what it lists.
    [$user, , $profile] = submitter();

    ResultTriage::fake([['hosts' => [['host' => 'annuaire.test', 'kind' => 'index', 'reason' => 'Lists businesses.']]]]);
    CompanyQualifier::fake([submittedVerdict(90), submittedVerdict(20)]);

    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://annuaire.test/friteries/namur' => Http::response(
            '<html lang="fr"><body><script type="application/ld+json">'
            .json_encode(['@type' => 'Restaurant', 'name' => 'Chez Marcel', 'url' => 'https://chez-marcel.test', 'telephone' => '+3281223344'])
            .'</script></body></html>'
        ),
        'https://chez-marcel.test/' => Http::response(submittedPage()),
        'https://annuaire.test/' => Http::response(submittedPage('Annuaire des commerces.')),
    ]);

    $this->actingAs($user)->post(route('companies.links.store'), [
        'target_profile' => $profile->id,
        'links' => 'https://annuaire.test/friteries/namur',
    ])->assertRedirect();

    expect(Company::pluck('domain')->sort()->values()->all())
        ->toBe(['annuaire.test', 'chez-marcel.test'])
        ->and(Company::pluck('source')->unique()->all())->toBe(['user-submitted']);
});

it('fails a link that is neither a company site nor a directory, without losing the rest of the batch', function () {
    [$user, , $profile] = submitter();
    $this->seed(KnownHostSeeder::class);

    ResultTriage::fake([['hosts' => [['host' => 'vraie-friterie.be', 'kind' => 'entity', 'reason' => 'One business.']]]]);
    CompanyQualifier::fake([submittedVerdict()]);

    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://vraie-friterie.be/' => Http::response(submittedPage()),
    ]);

    $this->actingAs($user)->post(route('companies.links.store'), [
        'target_profile' => $profile->id,
        // facebook.com is a locked `social` row seeded above: no model call
        // needed to know it is neither a company site nor a directory.
        'links' => "https://vraie-friterie.be\nhttps://www.facebook.com/some-page",
    ])->assertRedirect();

    expect(Company::count())->toBe(1);

    $failed = DiscoveryTask::query()
        ->where('kind', DiscoveryTaskKind::Classify)
        ->where('status', DiscoveryTaskStatus::Failed)
        ->sole();

    expect($failed->error)->toContain('facebook.com')->toContain('social');
});

it('never diagnoses a manual submission as too narrow or a bad target profile', function () {
    [$user, , $profile] = submitter();

    ResultTriage::fake([['hosts' => [['host' => 'vraie-friterie.be', 'kind' => 'entity', 'reason' => 'One business.']]]]);
    // Not a prospect: a search run would diagnose this `bad_target_profile`.
    CompanyQualifier::fake([['is_a_prospect' => false, 'fit_score' => 10, 'fit_reason' => 'Not a fit.']]);

    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://vraie-friterie.be/' => Http::response(submittedPage()),
    ]);

    $this->actingAs($user)->post(route('companies.links.store'), [
        'target_profile' => $profile->id,
        'links' => 'https://vraie-friterie.be',
    ])->assertRedirect();

    expect(DiscoveryRun::sole()->diagnosis)->toBeNull();
});
