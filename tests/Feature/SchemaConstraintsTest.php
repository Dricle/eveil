<?php

use App\Enums\CampaignLeadStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The two partial unique indexes encode business rules that no application code
 * would otherwise enforce. They are also the reason PostgreSQL is mandatory in
 * tests — SQLite would not reproduce either of them.
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
    return DB::table('leads')->insertGetId([
        'project_id' => $projectId,
        'email' => $email,
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
