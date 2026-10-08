<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Does this product have customers, and how far does anybody actually get.
 *
 * Counted from the application's own tables rather than from analytics, for
 * one reason that decides the whole design: this works RETROACTIVELY. Page
 * instrumentation can only ever answer for traffic that arrives after it
 * ships, which is no use when the question is what already happened. Every
 * milestone below is a row that has existed since the day it occurred.
 *
 * It is also the only honest place to count the two milestones with no browser
 * behind them - an outreach mail actually leaving, and a payment confirmed by
 * webhook. Both happen on a queue worker with nobody watching, and the
 * analytics ingest endpoint drops non-browser traffic silently rather than
 * rejecting it, so an event fired there would look fine and record nothing.
 *
 * Counted per ORGANIZATION, not per user or per project: the organization is
 * the billable entity, so "how many got this far" has to mean how many
 * potential customers, not how many times a keen one pressed the button.
 */
class FunnelReportCommand extends Command
{
    /**
     * Keyed by the JSON field so the machine-readable names stay stable while
     * the wording on screen can be rephrased freely.
     */
    private const LABELS = [
        'organizations' => 'Organizations created',
        'created_a_project' => '… created a project',
        'ran_a_site_analysis' => '… ran a site analysis',
        'derived_a_target_profile' => '… derived a target profile',
        'ran_a_lead_search' => '… ran a lead search',
        'got_at_least_one_lead' => '… got at least one lead',
        'created_a_sequence' => '… created a sequence',
        'sent_an_email' => '… actually sent an email',
        'paid_for_credits' => '… paid for credits',
    ];

    protected $signature = 'eveil:funnel-report
        {--exclude-internal= : Comma-separated organization names to leave out, e.g. our own dogfooding account.}
        {--json : Emit the figures as JSON instead of a table, for a dashboard or a scheduled digest.}';

    protected $description = 'Report how many organizations reached each stage of the product, and what they paid';

    public function handle(): int
    {
        $excluded = $this->excluded();

        $total = DB::table('organizations')
            ->when($excluded !== [], fn (Builder $query): Builder => $query->whereNotIn('name', $excluded))
            ->count();

        $report = [
            'organizations' => $total,
            'created_a_project' => $this->reached($excluded, $this->projectsOf()),
            'ran_a_site_analysis' => $this->reached($excluded, $this->analysesOf()),
            'derived_a_target_profile' => $this->reached($excluded, $this->targetProfilesOf()),
            'ran_a_lead_search' => $this->reached($excluded, $this->discoveryRunsOf()),
            'got_at_least_one_lead' => $this->reached($excluded, $this->leadsOf()),
            'created_a_sequence' => $this->reached($excluded, $this->campaignsOf()),
            'sent_an_email' => $this->reached($excluded, $this->sentMessagesOf()),
            'paid_for_credits' => $this->reached($excluded, $this->purchasesOf()),
        ];

        $totals = [
            'users' => DB::table('users')->count(),
            'projects' => DB::table('projects')->count(),
            'emails_sent' => DB::table('messages')
                ->where('direction', 'outbound')
                ->whereNotNull('sent_at')
                ->count(),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode(['funnel' => $report, 'totals' => $totals], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('Eveil funnel - every organization, since the beginning');

        if ($excluded !== []) {
            $this->comment('  Excluding: '.implode(', ', $excluded));
        }

        $this->line('');

        if ($total === 0) {
            $this->line('  No organizations exist. There is nothing to report and nobody to report on.');
            $this->line('');

            return self::SUCCESS;
        }

        $this->table(
            ['Stage', 'Organizations', 'Of all'],
            array_map(
                fn (string $stage, int $count): array => [
                    self::LABELS[$stage],
                    $count,
                    round($count / $total * 100).'%',
                ],
                array_keys($report),
                array_values($report),
            ),
        );

        $this->line('');
        $this->line("  Users: {$totals['users']}   Projects: {$totals['projects']}");
        $this->line("  Outbound emails actually sent, all time: {$totals['emails_sent']}");
        $this->line('');
        $this->comment('  Revenue: run `eveil:revenue --all`.');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * How many organizations appear at all in the given sub-query. Each
     * `…Of()` below returns a builder selecting an `organization_id`; counting
     * distinct values of it is what turns "423 leads" into "two customers got
     * leads", which is the only one of those two numbers that answers the
     * question being asked.
     *
     * @param  array<int, string>  $excluded
     */
    private function reached(array $excluded, Builder $source): int
    {
        return DB::query()
            ->fromSub($source, 'reached')
            ->join('organizations', 'organizations.id', '=', 'reached.organization_id')
            ->when($excluded !== [], fn (Builder $query): Builder => $query->whereNotIn('organizations.name', $excluded))
            ->distinct()
            ->count('reached.organization_id');
    }

    private function projectsOf(): Builder
    {
        return DB::table('projects')->select('organization_id');
    }

    private function analysesOf(): Builder
    {
        return DB::table('project_analyses')
            ->join('projects', 'projects.id', '=', 'project_analyses.project_id')
            ->select('projects.organization_id');
    }

    private function targetProfilesOf(): Builder
    {
        return DB::table('target_profiles')
            ->join('projects', 'projects.id', '=', 'target_profiles.project_id')
            ->select('projects.organization_id');
    }

    private function discoveryRunsOf(): Builder
    {
        return DB::table('discovery_runs')
            ->join('projects', 'projects.id', '=', 'discovery_runs.project_id')
            ->select('projects.organization_id');
    }

    private function leadsOf(): Builder
    {
        return DB::table('leads')
            ->join('projects', 'projects.id', '=', 'leads.project_id')
            ->select('projects.organization_id');
    }

    private function campaignsOf(): Builder
    {
        return DB::table('campaigns')
            ->join('projects', 'projects.id', '=', 'campaigns.project_id')
            ->select('projects.organization_id');
    }

    /**
     * Sent, not queued: a row that never left the building is not a milestone
     * anybody reached. `sent_at` rather than the status column because it is
     * the fact SMTP actually produced.
     */
    private function sentMessagesOf(): Builder
    {
        return DB::table('messages')
            ->join('leads', 'leads.id', '=', 'messages.lead_id')
            ->join('projects', 'projects.id', '=', 'leads.project_id')
            ->where('messages.direction', 'outbound')
            ->whereNotNull('messages.sent_at')
            ->select('projects.organization_id');
    }

    private function purchasesOf(): Builder
    {
        return DB::table('credit_transactions')
            ->where('type', 'grant_purchase')
            ->select('organization_id');
    }

    /**
     * @return array<int, string>
     */
    private function excluded(): array
    {
        $option = $this->option('exclude-internal');

        if (! is_string($option) || trim($option) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $option))));
    }
}
