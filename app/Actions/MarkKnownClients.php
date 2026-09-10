<?php

namespace App\Actions;

use App\Enums\OutreachStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Project;
use App\Support\Url;

/**
 * A client the user already has, told to the app before any search run finds
 * them by hand: an email or a website, marked `client` on arrival so the row
 * that would otherwise get created `new` by a later discovery run is never
 * written that way in the first place.
 *
 * Reuses the one status vocabulary and its one propagation path
 * (`SetOutreachStatus`) rather than a second "excluded" mechanism: two ways to
 * exclude a company is how the one somebody forgets ends up cold-mailed.
 */
class MarkKnownClients
{
    public function __construct(private SetOutreachStatus $setStatus) {}

    /**
     * @param  array<int, string>  $entries  one email address or one website per entry
     * @return array{companies: int, leads: int}
     */
    public function handle(Project $project, array $entries): array
    {
        $companies = 0;
        $leads = 0;

        foreach ($entries as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            if (filter_var($entry, FILTER_VALIDATE_EMAIL)) {
                $this->markLead($project, mb_strtolower($entry));
                $leads++;

                continue;
            }

            $domain = Url::host(Url::fromInput($entry));

            if ($domain === null || $domain === '') {
                continue;
            }

            $this->markCompany($project, mb_strtolower($domain));
            $companies++;
        }

        return ['companies' => $companies, 'leads' => $leads];
    }

    private function markLead(Project $project, string $email): void
    {
        /** @var Lead $lead */
        $lead = Lead::query()->firstOrCreate(
            ['project_id' => $project->id, 'email_hash' => Lead::hashFor($email)],
            ['email' => $email, 'source' => 'manual', 'discovered_at' => now()],
        );

        if ($lead->erased_at === null) {
            $this->setStatus->forLead($lead, OutreachStatus::Client);
        }
    }

    private function markCompany(Project $project, string $domain): void
    {
        /** @var Company $company */
        $company = Company::query()->firstOrCreate(
            ['project_id' => $project->id, 'domain' => $domain],
            ['name' => $domain, 'source' => 'manual', 'discovered_at' => now()],
        );

        $this->setStatus->forCompany($company, OutreachStatus::Client);
    }
}
