<?php

use App\Cloud\Models\CreditTransaction;
use App\Enums\MessageDirection;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Facades\Artisan;

/**
 * Asserted against `--json` rather than the rendered table: tying a stage name
 * to its number through a box-drawn table is a substring match that passes for
 * the wrong reasons, and the figures are the entire point of this command.
 *
 * @return array{funnel: array<string, int>, totals: array<string, int>}
 */
function funnelReport(array $options = []): array
{
    Artisan::call('eveil:funnel-report', $options + ['--json' => true]);

    return json_decode(Artisan::output(), true);
}

it('reports nothing to report when no organization exists', function () {
    $this->artisan('eveil:funnel-report')
        ->expectsOutputToContain('No organizations exist')
        ->assertSuccessful();
});

/**
 * The whole point of the report: an organization that signed up and stopped
 * must not be counted among those that got value out of the product.
 */
it('counts an organization that signed up and went no further at exactly one stage', function () {
    Organization::factory()->create();

    $report = funnelReport()['funnel'];

    expect($report['organizations'])->toBe(1)
        ->and($report['created_a_project'])->toBe(0)
        ->and($report['ran_a_lead_search'])->toBe(0)
        ->and($report['paid_for_credits'])->toBe(0);
});

it('counts an organization at each stage it reached', function () {
    $organization = Organization::factory()->create();
    Project::factory()->for($organization)->create();

    $report = funnelReport()['funnel'];

    expect($report['organizations'])->toBe(1)
        ->and($report['created_a_project'])->toBe(1)
        ->and($report['created_a_sequence'])->toBe(0);
});

/**
 * Counted per organization, not per row: a single keen customer creating four
 * projects is still one potential customer, and reporting four would make an
 * empty product look like a growing one.
 */
it('counts one organization once however many projects it created', function () {
    $organization = Organization::factory()->create();
    Project::factory()->count(4)->for($organization)->create();

    expect(funnelReport()['funnel']['created_a_project'])->toBe(1);
});

/**
 * Queued is not sent. A message that never left must not appear as a milestone
 * anybody reached - that is the difference between a product that works and
 * one that was merely configured.
 */
it('counts only emails that actually left, never ones still queued', function () {
    [$user, $project, $mailbox] = sender();
    $lead = contactable($project);

    Message::factory()->create([
        'lead_id' => $lead->id,
        'email_account_id' => $mailbox->id,
        'direction' => MessageDirection::Outbound,
        'sent_at' => null,
    ]);

    $report = funnelReport();

    expect($report['totals']['emails_sent'])->toBe(0)
        ->and($report['funnel']['sent_an_email'])->toBe(0);
});

it('counts an organization whose email did leave', function () {
    [$user, $project, $mailbox] = sender();
    $lead = contactable($project);

    Message::factory()->create([
        'lead_id' => $lead->id,
        'email_account_id' => $mailbox->id,
        'direction' => MessageDirection::Outbound,
        'sent_at' => now(),
    ]);

    $report = funnelReport();

    expect($report['totals']['emails_sent'])->toBe(1)
        ->and($report['funnel']['sent_an_email'])->toBe(1);
});

it('counts an organization that created a sequence', function () {
    [$user, $project, $mailbox] = sender();
    Campaign::factory()->create(['project_id' => $project->id]);

    expect(funnelReport()['funnel']['created_a_sequence'])->toBe(1);
});

it('counts an organization that paid', function () {
    $organization = Organization::factory()->create();

    CreditTransaction::factory()->for($organization)->create([
        'type' => 'grant_purchase',
        'credits' => 20000,
        'amount_cents' => 2000,
        'currency' => 'eur',
    ]);

    expect(funnelReport()['funnel']['paid_for_credits'])->toBe(1);
});

/**
 * A trial grant is not a payment. Counting it would report every signup as a
 * customer, which is the exact illusion this report exists to dispel.
 */
it('never counts a trial grant as having paid', function () {
    $organization = Organization::factory()->create();

    CreditTransaction::factory()->for($organization)->create(['type' => 'grant_trial', 'credits' => 5000]);

    expect(funnelReport()['funnel']['paid_for_credits'])->toBe(0);
});

/**
 * Our own dogfooding organization is the one account guaranteed to reach every
 * stage, and leaving it in makes a product with no customers look like a
 * product with one.
 */
it('leaves out an organization named on --exclude-internal, at every stage', function () {
    $internal = Organization::factory()->create(['name' => 'DRICLE LLP']);
    Organization::factory()->create(['name' => 'A real customer']);

    Project::factory()->for($internal)->create();

    $report = funnelReport(['--exclude-internal' => 'DRICLE LLP'])['funnel'];

    expect($report['organizations'])->toBe(1)
        ->and($report['created_a_project'])->toBe(0);
});
