<?php

use App\Ai\Agents\ContactExtractor;
use App\Enums\EmailSource;
use App\Enums\EmailStatus;
use App\Models\Company;
use App\Models\Erasure;
use App\Models\Lead;
use App\Models\Project;
use App\Services\Discovery\EmailPattern;
use App\Services\Discovery\EmailVerifier;
use Illuminate\Support\Facades\Http;

function extraction(array $people = [], array $generic = [], string $pattern = ''): array
{
    return [
        'people' => $people,
        'generic_emails' => $generic,
        'email_pattern' => $pattern,
        'phone' => '+32 71 00 00 00',
    ];
}

function contactPage(string $body = 'Contactez-nous'): string
{
    return '<!doctype html><html lang="fr"><head><title>Contact</title></head>'
        .'<body><p>'.$body.'</p><a href="/contact">Contact</a></body></html>';
}

function companyWithSite(?Project $project = null): Company
{
    $project ??= Project::factory()->create();

    return Company::factory()->create([
        'project_id' => $project->id,
        'domain' => 'friterie.be',
        'website' => 'https://friterie.be/',
        'language' => 'fr',
    ]);
}

beforeEach(function () {
    config()->set('eveil.crawl.delay_ms', 0);
    // The probe needs a real socket; verification is exercised on its own below.
    config()->set('eveil.verification.probe', false);

    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response(contactPage()),
    ]);
});

it('stores a named contact with the address published on the site', function () {
    companyWithSite();
    ContactExtractor::fake([extraction(people: [
        ['first_name' => 'Marie', 'last_name' => 'Dupont', 'title' => 'Gérante', 'email' => 'marie.dupont@friterie.be'],
    ])]);

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    $lead = Lead::sole();

    expect($lead->email)->toBe('marie.dupont@friterie.be')
        ->and($lead->first_name)->toBe('Marie')
        ->and($lead->title)->toBe('Gérante')
        ->and($lead->email_source)->toBe(EmailSource::Scraped)
        ->and($lead->language)->toBe('fr');
});

it('infers the other addresses from the shape of a published one', function () {
    companyWithSite();
    ContactExtractor::fake([extraction(people: [
        ['first_name' => 'Marie', 'last_name' => 'Dupont', 'title' => 'Gérante', 'email' => 'marie.dupont@friterie.be'],
        ['first_name' => 'Jean', 'last_name' => 'Martin', 'title' => 'Chef', 'email' => ''],
    ])]);

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    $jean = Lead::where('first_name', 'Jean')->sole();

    // The shape read off a real address beats anything the model claims.
    expect($jean->email)->toBe('jean.martin@friterie.be')
        ->and($jean->email_source)->toBe(EmailSource::Inferred);
});

it('falls back to a generic address when the site names nobody', function () {
    companyWithSite();
    ContactExtractor::fake([extraction(generic: ['info@friterie.be'])]);

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    // Weak, but at a one-person friterie it is often the only door.
    expect(Lead::sole()->email)->toBe('info@friterie.be');
});

it('prefers a named person over the front desk', function () {
    companyWithSite();
    ContactExtractor::fake([extraction(
        people: [['first_name' => 'Marie', 'last_name' => 'Dupont', 'title' => 'Gérante', 'email' => 'marie.dupont@friterie.be']],
        generic: ['info@friterie.be'],
    )]);

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    expect(Lead::pluck('email')->all())->toBe(['marie.dupont@friterie.be']);
});

it('never resurrects someone who asked to be erased', function () {
    $company = companyWithSite();
    Erasure::create([
        'organization_id' => $company->project->organization_id,
        'email_hash' => Erasure::hashFor('marie.dupont@friterie.be'),
        'requested_at' => now(),
    ]);

    ContactExtractor::fake([extraction(people: [
        ['first_name' => 'Marie', 'last_name' => 'Dupont', 'title' => 'Gérante', 'email' => 'marie.dupont@friterie.be'],
    ])]);

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    // Deleting the row is not enough, the next run would find her again.
    expect(Lead::count())->toBe(0);
});

it('skips companies that already have leads unless asked', function () {
    $company = companyWithSite();
    Lead::factory()->create(['project_id' => $company->project_id, 'company_id' => $company->id]);

    ContactExtractor::fake([extraction(generic: ['info@friterie.be'])]);

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    expect(Lead::count())->toBe(1);
});

it('keeps going when one company blows up', function () {
    $project = Project::factory()->create();
    companyWithSite($project);
    Company::factory()->create(['project_id' => $project->id, 'domain' => 'casse.be', 'website' => 'https://casse.be/']);

    ContactExtractor::fake(function ($prompt) {
        if (str_contains((string) $prompt, 'casse.be')) {
            throw new RuntimeException('unreadable');
        }

        return extraction(generic: ['info@friterie.be']);
    });

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    expect(Lead::sole()->email)->toBe('info@friterie.be');
});

describe('email pattern inference', function () {
    it('reads the shape out of a known address', function (string $email, string $expected) {
        expect(EmailPattern::detect($email, 'Marie', 'Dupont'))->toBe($expected);
    })->with([
        ['marie.dupont@x.be', 'first.last'],
        ['mariedupont@x.be', 'firstlast'],
        ['m.dupont@x.be', 'f.last'],
        ['mdupont@x.be', 'flast'],
        ['marie@x.be', 'first'],
        ['dupont.marie@x.be', 'last.first'],
    ]);

    it('gives up on a shape it does not recognise', function () {
        expect(EmailPattern::detect('sales-team-2024@x.be', 'Marie', 'Dupont'))->toBeNull();
    });

    it('strips accents, which no local part ever carries', function () {
        expect(EmailPattern::apply('first.last', 'Frédéric', 'Lemaître', 'x.be'))->toBe('frederic.lemaitre@x.be');
    });

    it('will not build an address without both names', function () {
        expect(EmailPattern::apply('first.last', 'Marie', '', 'x.be'))->toBeNull();
    });
});

describe('email verification', function () {
    it('rejects what it can actually disprove', function (string $email) {
        expect(app(EmailVerifier::class)->verify($email))->toBe(EmailStatus::Invalid);
    })->with([
        'not-an-email',
        'someone@mailinator.com',
        'someone@domain-that-does-not-exist-eveil-test.invalid',
    ]);

    it('returns unknown rather than invalid when the probe is off', function () {
        // Only `invalid` blocks a send, so it is reserved for proof. Gmail and
        // Microsoft refuse probes, and treating that as invalid would discard
        // most of the market.
        expect(app(EmailVerifier::class)->verify('someone@gmail.com'))->toBe(EmailStatus::Unknown);
    });
});

it('remembers the phone even when no email exists', function () {
    companyWithSite();
    ContactExtractor::fake([extraction()]);

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    // The first real run found phones on every friterie and not one address:
    // for this segment the phone is often the only way in.
    expect(Company::sole()->facts['phone'])->toBe('+32 71 00 00 00')
        ->and(Lead::count())->toBe(0);
});

it('reports companies that are reachable by phone only', function () {
    companyWithSite();
    ContactExtractor::fake([extraction()]);

    $this->artisan('eveil:find-contacts')
        ->expectsOutputToContain('Reachable by phone only')
        ->assertSuccessful();
});

it('does not guess a generic address unless asked', function () {
    companyWithSite();
    ContactExtractor::fake([extraction(), extraction()]);

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    expect(Lead::count())->toBe(0);
});
