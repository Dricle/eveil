<?php

use App\Enums\CampaignLeadStatus;
use App\Models\Lead;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The two partial unique indexes encode business rules that no application code
 * would otherwise enforce. They are also the reason PostgreSQL is mandatory in
 * tests: SQLite would not reproduce either of them.
 */
function makeProject(): int
{
    $organizationId = DB::table('organizations')->insertGetId([
        'name' => 'Acme',
        'slug' => 'acme-'.fake()->unique()->slug(2),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return DB::table('projects')->insertGetId([
        'organization_id' => $organizationId,
        'name' => 'Dricle',
        'url' => 'https://dricle.be',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function makeLead(int $projectId, ?string $email): int
{
    // Raw inserts on purpose: this file checks what the DATABASE refuses, not
    // what the model prevents. So the hash is written by hand here: in the app
    // the `email` mutator keeps it in step.
    return DB::table('leads')->insertGetId([
        'project_id' => $projectId,
        'email' => $email,
        'email_hash' => $email === null ? null : Lead::hashFor($email),
        'source' => 'test',
        'discovered_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('allows many leads without an email in one project', function () {
    $projectId = makeProject();

    makeLead($projectId, null);
    makeLead($projectId, null);

    expect(DB::table('leads')->where('project_id', $projectId)->count())->toBe(2);
});

it('rejects a duplicate email within one project', function () {
    $projectId = makeProject();
    makeLead($projectId, 'contact@example.com');

    makeLead($projectId, 'contact@example.com');
})->throws(QueryException::class);

it('accepts the same email across two different projects', function () {
    makeLead(makeProject(), 'contact@example.com');
    makeLead(makeProject(), 'contact@example.com');

    expect(DB::table('leads')->where('email', 'contact@example.com')->count())->toBe(2);
});

it('rejects a second live campaign membership for one lead', function () {
    $projectId = makeProject();
    $leadId = makeLead($projectId, 'contact@example.com');

    foreach (['First', 'Second'] as $name) {
        $campaignId = DB::table('campaigns')->insertGetId([
            'project_id' => $projectId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('campaign_leads')->insert([
            'campaign_id' => $campaignId,
            'lead_id' => $leadId,
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
})->throws(QueryException::class);

it('allows a new campaign once the previous membership is finished', function () {
    $projectId = makeProject();
    $leadId = makeLead($projectId, 'contact@example.com');

    foreach (['completed', 'running'] as $status) {
        $campaignId = DB::table('campaigns')->insertGetId([
            'project_id' => $projectId,
            'name' => 'Campaign '.$status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('campaign_leads')->insert([
            'campaign_id' => $campaignId,
            'lead_id' => $leadId,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    expect(DB::table('campaign_leads')->where('lead_id', $leadId)->count())->toBe(2);
});

it('keeps CampaignLeadStatus::live() in step with the partial index', function () {
    $definition = DB::table('pg_indexes')
        ->where('schemaname', 'public')
        ->where('indexname', 'campaign_leads_one_active_per_lead')
        ->value('indexdef');

    expect($definition)->not->toBeNull();

    foreach (CampaignLeadStatus::cases() as $status) {
        // The index is the enforcement; the enum is the readable copy. Drift
        // between them would silently let a lead into two live campaigns.
        expect(str_contains((string) $definition, "'{$status->value}'"))
            ->toBe($status->isLive(), "status {$status->value} disagrees with the index");
    }
});

function makeCompany(int $projectId, ?string $domain, string $name): int
{
    return DB::table('companies')->insertGetId([
        'project_id' => $projectId,
        'domain' => $domain,
        'name' => $name,
        'source' => 'directory',
        'discovered_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('still dedupes companies by domain once the column may be null', function () {
    // Postgres treats every NULL as distinct, so a plain unique index stops
    // deduping the moment the column becomes nullable. The partial one does
    // the work the whole no-duplicate-lead promise rests on.
    $project = makeProject();
    makeCompany($project, 'friterie.be', 'Friterie du Centre');

    expect(fn () => makeCompany($project, 'friterie.be', 'Friterie du Centre'))
        ->toThrow(QueryException::class);
});

it('dedupes a site-less company on its name, whatever the directory capitalised', function () {
    // With no domain the name is the only key every source supplies, so it has
    // to hold on its own. A re-run reads the very same listing page.
    $project = makeProject();
    makeCompany($project, null, 'Chez Marcel');

    expect(fn () => makeCompany($project, null, 'chez marcel'))->toThrow(QueryException::class);
});

it('lets another project find the same site-less company', function () {
    // The row is project-scoped like everything else: two projects finding the
    // same business is two companies, not a duplicate.
    $project = makeProject();
    $other = makeProject();

    makeCompany($project, null, 'Chez Marcel');

    expect(makeCompany($other, null, 'Chez Marcel'))->toBeInt();
});
