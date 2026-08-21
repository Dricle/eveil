<?php

use App\Actions\ApproveCompanies;
use App\Actions\EnrolCampaign;
use App\Enums\AutonomyLevel;
use App\Enums\CampaignStatus;
use App\Enums\ContactSearchStatus;
use App\Enums\EmailSource;
use App\Enums\EmailStatus;
use App\Enums\OutreachStatus;
use App\Jobs\FindCompanyContacts;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\Company;
use App\Models\CompanyTargetEvaluation;
use App\Models\Lead;
use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Support\Facades\Queue;

/**
 * A lead at a company, which is the shape everything discovery produces. The
 * helper in `Pest.php` makes a person with no company, which is the imported
 * shape, and the two are governed by different rules.
 */
function atCompany(Project $project, Company $company, string $email = 'marcel@friterie.test'): Lead
{
    return Lead::factory()->create([
        'project_id' => $project->id,
        'company_id' => $company->id,
        'email' => $email,
        'email_status' => EmailStatus::Valid,
        'status' => OutreachStatus::New,
    ]);
}

it('keeps an unapproved company out of the sequence, and lets it in once approved', function () {
    [$user, $project] = sender();

    $company = Company::factory()->create(['project_id' => $project->id]);
    atCompany($project, $company);
    $campaign = sequence($project);

    // The project ships on the middle setting, where the user's go-ahead on
    // the company is what opens the door.
    expect($project->autonomy_level)->toBe(AutonomyLevel::SemiAuto)
        ->and(app(EnrolCampaign::class)->handle($campaign))->toBe(0);

    $company->update(['approved_at' => now()]);

    expect(app(EnrolCampaign::class)->handle($campaign))->toBe(1);

    unset($user);
});

it('writes to anyone contactable once the project is left to itself', function () {
    [, $project] = sender();

    $project->update(['autonomy_level' => AutonomyLevel::Autonomous]);

    $company = Company::factory()->create(['project_id' => $project->id]);
    atCompany($project, $company);

    // Nobody approved this company. Full autonomy is exactly the setting that
    // says not to wait for that.
    expect(app(EnrolCampaign::class)->handle(sequence($project)))->toBe(1);
});

it('enrols a person nobody could approve, because they have no company', function () {
    [, $project] = sender();

    contactable($project);

    // An imported list has no companies to approve. Requiring one would mean a
    // CSV import silently never receives anything.
    expect(app(EnrolCampaign::class)->handle(sequence($project)))->toBe(1);
});

it('only takes the people its own segment found', function () {
    [, $project] = sender();

    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);
    $other = TargetProfile::factory()->create(['project_id' => $project->id]);

    $mine = Company::factory()->create(['project_id' => $project->id, 'approved_at' => now()]);
    $theirs = Company::factory()->create(['project_id' => $project->id, 'approved_at' => now()]);

    CompanyTargetEvaluation::factory()->create([
        'company_id' => $mine->id, 'target_profile_id' => $profile->id,
    ]);
    CompanyTargetEvaluation::factory()->create([
        'company_id' => $theirs->id, 'target_profile_id' => $other->id,
    ]);

    atCompany($project, $mine, 'mine@friterie.test');
    atCompany($project, $theirs, 'theirs@friterie.test');

    $campaign = sequence($project);
    $campaign->update(['target_profile_id' => $profile->id]);

    app(EnrolCampaign::class)->handle($campaign);

    // A sequence is written from one segment's fit reason. Sending it to
    // somebody another profile found makes the opener talk about the wrong
    // thing, and with one campaign the mistake is invisible.
    expect(CampaignLead::query()->with('lead')->get()->pluck('lead.email')->all())
        ->toBe(['mine@friterie.test']);
});

it('sends the addresses nobody confirmed out last', function () {
    [, $project] = sender();

    $company = Company::factory()->create(['project_id' => $project->id, 'approved_at' => now()]);

    $guessed = atCompany($project, $company, 'info@friterie.test');
    $guessed->update(['first_name' => null, 'email_source' => EmailSource::Inferred]);

    $named = atCompany($project, $company, 'marie@friterie.test');
    $named->update(['first_name' => 'Marie', 'email_source' => EmailSource::Scraped]);

    app(EnrolCampaign::class)->handle(sequence($project));

    $order = CampaignLead::query()->with('lead')->orderBy('next_action_at')->get()->pluck('lead.email');

    // The bounce circuit breaker pauses a mailbox at five percent. If a batch
    // of guesses is going to trip it, it must trip after the addresses we are
    // sure of have already left.
    expect($order->all())->toBe(['marie@friterie.test', 'info@friterie.test']);
});

it('starts the search for people when a company is approved, and only when nobody has looked', function () {
    Queue::fake();

    [, $project] = sender();

    $fresh = Company::factory()->create(['project_id' => $project->id, 'contacts_status' => null]);
    $alreadyRead = Company::factory()->create([
        'project_id' => $project->id,
        'contacts_status' => ContactSearchStatus::Done,
    ]);

    app(ApproveCompanies::class)->handle(collect([$fresh, $alreadyRead]));

    // Approving and then waiting for a second click is the click this exists
    // to remove. Asking again for a company that came back empty is not.
    Queue::assertPushed(FindCompanyContacts::class, 1);
    Queue::assertPushed(fn (FindCompanyContacts $job): bool => $job->company->is($fresh) && $job->guessGeneric);

    expect($fresh->fresh()->contacts_status)->toBe(ContactSearchStatus::Queued)
        ->and($alreadyRead->fresh()->contacts_status)->toBe(ContactSearchStatus::Done);
});

it('approves a batch from the list and takes one back', function () {
    Queue::fake();

    [$user, $project] = sender();

    $companies = Company::factory()->count(3)->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->putJson(route('companies.approval'), ['companies' => $companies->pluck('id')->all()])
        ->assertRedirect();

    expect(Company::query()->approved()->count())->toBe(3);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->putJson(route('companies.approval'), [
            'companies' => [$companies->first()->id],
            'approved' => false,
        ]);

    expect(Company::query()->approved()->count())->toBe(2);
});

it('never approves a company belonging to another project', function () {
    [$user, $project] = sender();

    $someoneElses = Company::factory()->create();

    // The ids come from a form. The project scope is what makes them safe: a
    // foreign row is simply not found, so tampering approves nothing.
    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->putJson(route('companies.approval'), ['companies' => [$someoneElses->id]]);

    expect($someoneElses->fresh()->approved_at)->toBeNull();
});

it('picks up the people found after a campaign was already running', function () {
    [, $project] = sender();

    $campaign = sequence($project);
    $campaign->update(['status' => CampaignStatus::Active]);

    // The case that used to be invisible: contact extraction lands in waves,
    // and everyone in a wave after the campaign started stayed outside it for
    // good.
    $company = Company::factory()->create(['project_id' => $project->id, 'approved_at' => now()]);
    atCompany($project, $company);

    $this->artisan('eveil:enrol-due')->assertSuccessful();

    expect(CampaignLead::query()->count())->toBe(1);
});

it('leaves a supervised project alone', function () {
    [, $project] = sender();

    $project->update(['autonomy_level' => AutonomyLevel::Supervised]);

    $campaign = sequence($project);
    $campaign->update(['status' => CampaignStatus::Active]);

    $company = Company::factory()->create(['project_id' => $project->id, 'approved_at' => now()]);
    atCompany($project, $company);

    $this->artisan('eveil:enrol-due')->assertSuccessful();

    // Supervised means the user decides WHEN as well as who: starting the
    // campaign by hand is the whole of their control, and a tick enrolling
    // behind them would take it away.
    expect(CampaignLead::query()->count())->toBe(0);

    // And the same campaign, started by hand, still takes them.
    expect(app(EnrolCampaign::class)->handle($campaign))->toBe(1);
});

it('does not enrol into a campaign that is not running', function () {
    [, $project] = sender();

    $campaign = sequence($project);
    $company = Company::factory()->create(['project_id' => $project->id, 'approved_at' => now()]);
    atCompany($project, $company);

    $this->artisan('eveil:enrol-due')->assertSuccessful();

    expect(CampaignLead::query()->count())->toBe(0);
    unset($campaign);
});
