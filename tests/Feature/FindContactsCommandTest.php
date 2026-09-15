<?php

use App\Ai\Agents\ContactExtractor;
use App\Ai\Agents\ResultTriage;
use App\Enums\EmailSource;
use App\Enums\EmailStatus;
use App\Enums\MessageDirection;
use App\Enums\ProbeOutcome;
use App\Enums\SuppressionLayer;
use App\Models\Company;
use App\Models\EmailAccount;
use App\Models\Lead;
use App\Models\MailHost;
use App\Models\Message;
use App\Models\Project;
use App\Models\Suppression;
use App\Services\Discovery\EmailPattern;
use App\Services\Discovery\EmailVerifier;
use App\Support\DisposableDomains;
use App\Support\Settings;
use Database\Seeders\DisposableDomainSeeder;
use Database\Seeders\MailHostSeeder;
use Illuminate\Http\Client\Factory;
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
    app(Settings::class)->set('crawl.delay_ms', 0);
    // The probe needs a real socket; verification is exercised on its own below.
    app(Settings::class)->set('verification.probe', false);

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

    Lead::factory()->create([
        'project_id' => $company->project_id,
        'company_id' => $company->id,
        'email' => 'marie.dupont@friterie.be',
    ])->erase();

    ContactExtractor::fake([extraction(people: [
        ['first_name' => 'Marie', 'last_name' => 'Dupont', 'title' => 'Gérante', 'email' => 'marie.dupont@friterie.be'],
    ])]);

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    // The stripped row stays. It IS the tombstone, but nothing is re-created
    // and no address comes back.
    expect(Lead::count())->toBe(1)
        ->and(Lead::sole()->email)->toBeNull()
        ->and(Lead::sole()->first_name)->toBeNull()
        ->and(Lead::sole()->erased_at)->not->toBeNull();
});

it('wipes the messages it already sent, not just the lead', function () {
    $company = companyWithSite();
    $lead = Lead::factory()->create(['project_id' => $company->project_id, 'company_id' => $company->id]);

    $account = EmailAccount::factory()->create(['organization_id' => $company->project->organization_id]);
    Message::create([
        'lead_id' => $lead->id,
        'email_account_id' => $account->id,
        'direction' => MessageDirection::Outbound,
        'message_id' => 'a@b',
        'subject' => 'Bonjour Marie',
        'body' => 'Marie, je vous écris à marie.dupont@friterie.be…',
    ]);

    $lead->erase();

    // The copy we sent carries her name and address in the body: deleting the
    // lead alone would leave the personal data sitting in `messages`.
    $message = Message::sole();

    expect($message->subject)->toBe('')
        ->and($message->body)->toBe('')
        ->and($lead->fresh()->email_hash)->not->toBeNull();
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
        ['marie_dupont@x.be', 'first_last'],
        ['m.dupont@x.be', 'f.last'],
        ['mdupont@x.be', 'flast'],
        ['marie@x.be', 'first'],
        ['dupont@x.be', 'last'],
        ['dupont.marie@x.be', 'last.first'],

        // None of these were in the hand-written list of eight shapes that this
        // replaced. A shape it cannot read is not a quiet miss: the site's real
        // convention is lost and the fallback guesses one that bounces.
        ['marie-dupont@x.be', 'first-last'],
        ['dupont-marie@x.be', 'last-first'],
        ['m_dupont@x.be', 'f_last'],
        ['maried@x.be', 'firstl'],
        ['d.marie@x.be', 'l.first'],
        ['marie.d@x.be', 'first.l'],
    ]);

    it('round-trips every shape it can read onto another person', function (string $email) {
        $shape = EmailPattern::detect($email, 'Marie', 'Dupont');

        expect($shape)->not->toBeNull()
            ->and(EmailPattern::apply($shape, 'Jean', 'Martin', 'y.be'))->not->toBeNull();
    })->with(['marie-dupont@x.be', 'm_dupont@x.be', 'maried@x.be', 'd.marie@x.be', 'dupont.marie@x.be']);

    it('prefers the full name over the initial when both would fit', function () {
        // Marie shortened to M: `m.dupont` is `first.last`, not `f.last`, and
        // guessing the latter would write `j.martin` where `jean.martin` is right.
        expect(EmailPattern::detect('m.dupont@x.be', 'M', 'Dupont'))->toBe('first.last');
    });

    it('refuses two bare initials, which identify nobody', function () {
        // `md@` fits half a company. Inferring anyone else's address from it
        // produces bounces, and bounces cost the sending domain.
        expect(EmailPattern::detect('md@x.be', 'Marie', 'Dupont'))->toBeNull();
    });

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
        // The blocklist lives in the database now, so it has to be loaded.
        // Without it `mailinator.com` reaches the MX check, which is a real
        // DNS call, and mailinator has perfectly good MX records.
        $this->seed(DisposableDomainSeeder::class);

        expect(app(EmailVerifier::class)->verify($email))->toBe(EmailStatus::Invalid);
    })->with([
        'not-an-email',
        'someone@mailinator.com',
        'someone@domain-that-does-not-exist-eveil-test.invalid',
    ]);

    it('loads a blocklist far larger than anyone would maintain by hand', function () {
        // Twelve domains used to be listed in the verifier. A throwaway domain
        // has working MX and passes every other check, so each one missing was
        // an address marked valid and sent to.
        $this->seed(DisposableDomainSeeder::class);

        expect(Suppression::query()->where('layer', SuppressionLayer::Toxic)->count())
            ->toBeGreaterThan(5_000)
            ->and(app(DisposableDomains::class)->includes('mailinator.com'))->toBeTrue()
            ->and(app(DisposableDomains::class)->includes('laravel.com'))->toBeFalse();
    });

    it('replaces the whole set rather than merging into it', function () {
        // A refresh that half-succeeded would leave a partial blocklist, which
        // silently starts accepting throwaways it used to reject.
        $disposable = app(DisposableDomains::class);

        $disposable->replaceWith(['old-throwaway.test', 'mailinator.com']);
        $disposable->replaceWith(['new-throwaway.test']);

        expect($disposable->includes('new-throwaway.test'))->toBeTrue()
            ->and($disposable->includes('old-throwaway.test'))->toBeFalse()
            ->and(Suppression::query()->where('layer', SuppressionLayer::Toxic)->count())->toBe(1);
    });

    it('refuses a truncated upstream list instead of shrinking the blocklist', function () {
        $this->seed(DisposableDomainSeeder::class);
        $before = Suppression::query()->where('layer', SuppressionLayer::Toxic)->count();

        Http::fake(['*' => Http::response("only-one.test\n")]);

        $this->artisan('eveil:refresh-disposable')
            ->expectsOutputToContain('Refusing to replace')
            ->assertFailed();

        expect(Suppression::query()->where('layer', SuppressionLayer::Toxic)->count())->toBe($before);
    });

    it('skips a provider known to refuse, without spending the timeout', function () {
        // Nine provider names used to be hardcoded. A miss was never a wrong
        // answer: the probe returns nothing and we say `unknown` either way.
        // It just cost the timeout every time.
        $this->seed(MailHostSeeder::class);
        app(Settings::class)->set('verification.probe', true);

        expect(MailHost::query()->firstWhere('host', 'google.com')->refusesProbes())->toBeTrue()
            // Learned rows need evidence; a shipped certainty does not.
            ->and(MailHost::query()->firstWhere('host', 'google.com')->is_locked)->toBeTrue();
    });

    it('marks a host as refusing only after it has consistently said nothing', function () {
        $host = MailHost::factory()->create(['attempts' => 2, 'refusals' => 2]);

        // Two silences is greylisting or bad luck, not a policy.
        expect($host->refusesProbes())->toBeFalse()
            ->and(MailHost::factory()->refusing()->create()->refusesProbes())->toBeTrue()
            // One verdict among the silences means it does answer, sometimes.
            ->and(MailHost::factory()->create(['attempts' => 9, 'refusals' => 8])->refusesProbes())->toBeFalse();
    });

    it('learns nothing from a probe that never reached a server', function () {
        // Port 25 is blocked on most hosting. Counting that as a refusal would
        // have the first run on such a box mark every mail provider on earth
        // as one, and then never probe again, anywhere.
        expect(ProbeOutcome::Unreachable->isEvidence())->toBeFalse()
            ->and(ProbeOutcome::NoVerdict->isEvidence())->toBeTrue()
            ->and(ProbeOutcome::NoVerdict->isVerdict())->toBeFalse()
            ->and(ProbeOutcome::Accepted->isVerdict())->toBeTrue();
    });

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

it('keeps a guessed address only when the mail server confirms it', function () {
    companyWithSite();
    ContactExtractor::fake([extraction(), extraction()]);

    // `info@` is refused, `contact@` exists. Everything in between, the
    // catch-all and the server that will not answer, means "we did not
    // disprove it", which is not the same as "it is there".
    $this->app->bind(EmailVerifier::class, fn () => new class extends EmailVerifier
    {
        public function __construct() {}

        public function verify(string $email): EmailStatus
        {
            return match (mb_strstr($email, '@', before_needle: true)) {
                'contact' => EmailStatus::Valid,
                'info' => EmailStatus::Risky,
                default => EmailStatus::Unknown,
            };
        }
    });

    $this->artisan('eveil:find-contacts', ['--guess-generic' => true])->assertSuccessful();

    $lead = Lead::sole();

    // A guess nobody confirmed is a bounce, and five bounces in a hundred
    // sends pause the mailbox for every good address behind them.
    expect($lead->email)->toBe('contact@friterie.be')
        ->and($lead->email_source)->toBe(EmailSource::Inferred);
});

it('guesses nothing at all when no candidate can be confirmed', function () {
    companyWithSite();
    ContactExtractor::fake([extraction(), extraction()]);

    // The shape of a provider that refuses probes, which is most of the
    // market. The step disables itself rather than pretending.
    $this->app->bind(EmailVerifier::class, fn () => new class extends EmailVerifier
    {
        public function __construct() {}

        public function verify(string $email): EmailStatus
        {
            return EmailStatus::Unknown;
        }
    });

    $this->artisan('eveil:find-contacts', ['--guess-generic' => true])->assertSuccessful();

    expect(Lead::count())->toBe(0);
});

it('does not guess a generic address unless asked', function () {
    companyWithSite();
    ContactExtractor::fake([extraction(), extraction()]);

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    expect(Lead::count())->toBe(0);
});

it('takes the address a directory published when the business has no site', function () {
    // Nothing to crawl and nobody to name: the listing line is the whole of
    // what exists, and half this segment is unreachable by email at all, so
    // the one address the directory printed is not a fallback, it is the lead.
    $company = Company::factory()->create([
        'domain' => null,
        'website' => null,
        'language' => 'fr',
        'source_url' => 'https://annuaire.test/friteries/namur',
        'facts' => ['email' => 'marcel@chez-marcel.test', 'phone' => '+3281223344'],
    ]);

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    $lead = Lead::sole();

    expect($lead->email)->toBe('marcel@chez-marcel.test')
        ->and($lead->company_id)->toBe($company->id)
        ->and($lead->email_source)->toBe(EmailSource::Scraped)
        ->and($lead->source_url)->toBe('https://annuaire.test/friteries/namur');
});

it('finds the website for a registry record before giving up, then extracts as normal', function () {
    // A registry record publishes a legal name and an address, never a
    // website or an email: one search-engine shot at the real site, trusted
    // only because the page it lands on actually names the company.
    $company = Company::factory()->create([
        'domain' => null,
        'website' => null,
        'name' => 'Friterie du Centre',
        'language' => 'fr',
        'source' => 'registry',
        'facts' => ['address' => 'Rue Jules Delhaize 51 1080 Molenbeek-Saint-Jean', 'registration_number' => '0821.017.106'],
    ]);

    ContactExtractor::fake([extraction(people: [
        ['first_name' => 'Marcel', 'last_name' => 'Dupont', 'title' => 'Gérant', 'email' => 'marcel@friterie-du-centre.be'],
    ])]);

    // `beforeEach`'s own `Http::fake(['*' => ...])` is already registered by
    // the time this runs, and Laravel resolves a faked response by taking the
    // FIRST stub (in registration order) that matches - a `'*'` pattern
    // matches everything, so it would win over anything added below no
    // matter how specific. Swapping in a fresh factory clears it so this
    // test's own patterns are the only ones in play.
    Http::swap(new Factory);
    Http::fake([
        'http://searxng:8080/search*' => Http::response(['results' => [
            ['url' => 'https://friterie-du-centre.be', 'title' => 'Friterie du Centre'],
        ]]),
        '*/robots.txt' => Http::response('', 404),
        'https://friterie-du-centre.be*' => Http::response(
            '<!doctype html><html lang="fr"><head><title>Friterie du Centre</title></head>'
            .'<body><p>Friterie du Centre, votre friterie a Molenbeek.</p><a href="/contact">Contact</a></body></html>'
        ),
        '*' => Http::response(contactPage()),
    ]);

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    $lead = Lead::sole();

    expect($lead->email)->toBe('marcel@friterie-du-centre.be')
        ->and($company->fresh()->domain)->toBe('friterie-du-centre.be');
});

it('does not enrich a registry record when the top hit does not actually name the company', function () {
    $company = Company::factory()->create([
        'domain' => null,
        'website' => null,
        'name' => 'Friterie du Centre',
        'facts' => ['address' => 'Rue Jules Delhaize 51 1080 Molenbeek-Saint-Jean'],
    ]);

    // See the previous test: clears `beforeEach`'s catch-all so it cannot
    // shadow this test's own, more specific stubs.
    Http::swap(new Factory);
    Http::fake([
        'http://searxng:8080/search*' => Http::response(['results' => [
            ['url' => 'https://unrelated-business.be', 'title' => 'Unrelated Business'],
        ]]),
        '*/robots.txt' => Http::response('', 404),
        // The wrong site: never mentions the company being looked for.
        '*' => Http::response(contactPage()),
    ]);

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    expect(Lead::count())->toBe(0)
        ->and($company->fresh()->domain)->toBeNull();
});

it('skips a directory result and finds the company on its own site instead', function () {
    // A "name + address" search just as often surfaces the very directory or
    // blog post the address came from, which names the company too - a plain
    // "does the page mention the name" check cannot tell that apart from the
    // company's real site. The same host classifier discovery already uses
    // to sort a search result into a directory or a company has to run here
    // first, so a directory is never even considered a candidate.
    $company = Company::factory()->create([
        'domain' => null,
        'website' => null,
        'name' => 'Friterie du Centre',
        'facts' => ['address' => 'Rue Jules Delhaize 51 1080 Molenbeek-Saint-Jean'],
    ]);

    ContactExtractor::fake([extraction(people: [
        ['first_name' => 'Marcel', 'last_name' => 'Dupont', 'title' => 'Gérant', 'email' => 'marcel@friterie-du-centre.be'],
    ])]);
    ResultTriage::fake([['hosts' => [
        ['host' => 'annuaire.test', 'kind' => 'index', 'reason' => 'Lists businesses.'],
        ['host' => 'friterie-du-centre.be', 'kind' => 'entity', 'reason' => 'One business.'],
    ]]]);

    Http::swap(new Factory);
    Http::fake([
        'http://searxng:8080/search*' => Http::response(['results' => [
            // The directory ranks first: it must still be skipped.
            ['url' => 'https://annuaire.test/friteries/namur', 'title' => 'Friterie du Centre - annuaire.test'],
            ['url' => 'https://friterie-du-centre.be', 'title' => 'Friterie du Centre'],
        ]]),
        '*/robots.txt' => Http::response('', 404),
        'https://annuaire.test*' => Http::response(
            '<!doctype html><html lang="fr"><head><title>Annuaire</title></head>'
            .'<body><p>Friterie du Centre, Rue Jules Delhaize 51.</p></body></html>'
        ),
        'https://friterie-du-centre.be*' => Http::response(
            '<!doctype html><html lang="fr"><head><title>Friterie du Centre</title></head>'
            .'<body><p>Friterie du Centre, votre friterie a Molenbeek.</p><a href="/contact">Contact</a></body></html>'
        ),
        '*' => Http::response(contactPage()),
    ]);

    $this->artisan('eveil:find-contacts')->assertSuccessful();

    expect($company->fresh()->domain)->toBe('friterie-du-centre.be')
        ->and(Lead::sole()->email)->toBe('marcel@friterie-du-centre.be');
});
