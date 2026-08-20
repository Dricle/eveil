<?php

use App\Enums\EmailSource;
use App\Enums\SuppressionLayer;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Suppression;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * A list somebody already had. The rule that shapes the whole feature: a row is
 * worth keeping when it carries an email OR a LinkedIn URL, and every row that
 * does not land comes back with its line number and the reason.
 */
function importer(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);

    return [$user, Project::factory()->for($organization)->create()];
}

function csvFile(string $body): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    file_put_contents($path, $body);

    return new UploadedFile($path, 'contacts.csv', 'text/csv', null, true);
}

it('imports a row carrying an address and links its company', function () {
    [$user, $project] = importer();

    $this->actingAs($user)->post(route('contacts.import'), [
        'file' => csvFile(
            "email,first_name,last_name,title,linkedin_url,company_name,company_domain\n"
            ."jean@example.com,Jean,Martin,Operations manager,,Example SA,https://www.example.com/\n"
        ),
    ])->assertRedirect(route('contacts.index'));

    $lead = Lead::withoutGlobalScopes()->sole();
    $company = Company::withoutGlobalScopes()->sole();

    expect($lead->email)->toBe('jean@example.com')
        ->and($lead->project_id)->toBe($project->id)
        ->and($lead->first_name)->toBe('Jean')
        ->and($lead->email_source)->toBe(EmailSource::Imported)
        // Deliberately unverified: an SMTP probe per row would mean minutes of
        // spinner. The pre-send check is where an address proves itself.
        ->and($lead->email_status)->toBeNull()
        ->and($lead->source)->toBe('import')
        ->and($company->domain)->toBe('example.com')
        ->and($lead->company_id)->toBe($company->id);
});

it('keeps a row that has only a LinkedIn URL', function () {
    [$user] = importer();

    // Refusing these would throw away the half of a hand-built list another
    // channel can still reach, and the schema already allows an addressless lead.
    $this->actingAs($user)->post(route('contacts.import'), [
        'file' => csvFile(
            "email,first_name,linkedin_url\n"
            .",Sofia,https://www.linkedin.com/in/sofia\n"
        ),
    ]);

    $lead = Lead::withoutGlobalScopes()->sole();

    expect($lead->email)->toBeNull()
        ->and($lead->linkedin_url)->toBe('https://www.linkedin.com/in/sofia');
});

it('reports every row it could not keep, with its line and its reason', function () {
    [$user] = importer();

    $response = $this->actingAs($user)->post(route('contacts.import'), [
        'file' => csvFile(
            "email,first_name,linkedin_url\n"
            ."jean@example.com,Jean,\n"          // line 2, kept
            .",Nobody,\n"                        // line 3, neither
            ."not-an-address,Broken,\n"          // line 4, malformed
            ."jean@example.com,Twice,\n"         // line 5, already in this file
        ),
    ]);

    $report = $response->baseResponse->getSession()->get('import');

    expect($report['imported'])->toBe(1)
        ->and($report['rejected_count'])->toBe(3)
        ->and($report['rejected'][0])->toBe([
            'line' => 3, 'value' => '', 'reason' => 'Neither an email address nor a LinkedIn URL.',
        ])
        ->and($report['rejected'][1]['reason'])->toBe('Not an email address.')
        ->and($report['rejected'][2]['reason'])->toBe('Appears twice in this file.');
});

it('counts a person already in the project as a duplicate rather than a second row', function () {
    [$user, $project] = importer();

    Lead::withoutGlobalScopes()->create([
        'project_id' => $project->id,
        'email' => 'jean@example.com',
        'source' => 'scraped',
        'discovered_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('contacts.import'), [
        'file' => csvFile("email\njean@example.com\n"),
    ]);

    expect($response->baseResponse->getSession()->get('import')['duplicates'])->toBe(1)
        ->and(Lead::withoutGlobalScopes()->count())->toBe(1);
});

it('refuses to put back somebody who asked to be forgotten', function () {
    [$user, $project] = importer();

    $lead = Lead::withoutGlobalScopes()->create([
        'project_id' => $project->id,
        'email' => 'gone@example.com',
        'source' => 'scraped',
        'discovered_at' => now(),
    ]);

    $lead->erase();

    $response = $this->actingAs($user)->post(route('contacts.import'), [
        'file' => csvFile("email\ngone@example.com\n"),
    ]);

    // The request outlives the data it destroyed. A file must not be the way
    // back in.
    expect($response->baseResponse->getSession()->get('import')['rejected'][0]['reason'])
        ->toBe('This person asked to be forgotten.')
        ->and(Lead::withoutGlobalScopes()->whereNull('erased_at')->count())->toBe(0);
});

it('refuses an address on the suppression list', function () {
    [$user, $project] = importer();

    Suppression::create([
        'layer' => SuppressionLayer::OptOut,
        'project_id' => $project->id,
        'email' => 'stop@example.com',
        'reason' => 'Replied STOP.',
    ]);

    $response = $this->actingAs($user)->post(route('contacts.import'), [
        'file' => csvFile("email\nstop@example.com\n"),
    ]);

    expect($response->baseResponse->getSession()->get('import')['rejected'][0]['reason'])
        ->toBe('On the suppression list.')
        ->and(Lead::withoutGlobalScopes()->count())->toBe(0);
});

it('reads the columns in whatever order they were exported', function () {
    [$user] = importer();

    $this->actingAs($user)->post(route('contacts.import'), [
        'file' => csvFile("First Name,EMAIL\nSofia,sofia@example.com\n"),
    ]);

    expect(Lead::withoutGlobalScopes()->sole()->first_name)->toBe('Sofia');
});

it('rejects a file that is not a CSV', function () {
    [$user] = importer();

    $this->actingAs($user)
        ->post(route('contacts.import'), ['file' => UploadedFile::fake()->create('leads.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('file');
});

it('hands out a template with the columns the parser reads', function () {
    [$user] = importer();

    $this->actingAs($user)->get(route('contacts.template'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertSee('email,first_name,last_name,title,linkedin_url,company_name,company_domain');
});

it('shows an imported contact on the list even though nothing has verified it', function () {
    [$user] = importer();

    $this->actingAs($user)->post(route('contacts.import'), [
        'file' => csvFile("email\njean@example.com\n"),
    ]);

    // `email_status != 'invalid'` is NULL for an unchecked address, so the
    // default filter has to ask for the null case explicitly or every imported
    // row is invisible.
    $this->actingAs($user)->get(route('contacts.index'))
        ->assertInertia(fn ($page) => $page->has('contacts.data', 1));
});
