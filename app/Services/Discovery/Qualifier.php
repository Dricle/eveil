<?php

namespace App\Services\Discovery;

use App\Ai\Agents\CompanyQualifier;
use App\Models\AgentRun;
use App\Models\Company;
use App\Models\CompanyTargetEvaluation;
use App\Models\DiscoveryRun;
use App\Models\TargetProfile;
use App\Support\HtmlText;
use App\Support\ParsedPage;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * One candidate's own site, read and scored against the profile. This is where
 * a name found somewhere becomes a company worth writing to — or does not.
 */
class Qualifier
{
    public function __construct(private PageFetcher $fetcher, private HtmlText $html) {}

    /**
     * Whether the candidate turned out to be a prospect and was stored.
     */
    public function qualify(
        TargetProfile $targetProfile,
        DiscoveryRun $run,
        Candidate $candidate,
        ?AgentRun $agentRun = null,
    ): bool {
        if ($candidate->website === null) {
            return false;
        }

        $page = $this->fetcher->fetch($candidate->website);

        if ($page === null) {
            return false;
        }

        $parsed = $this->html->parse((string) $page->content, $candidate->website);

        if ($parsed->isEmpty()) {
            return false;
        }

        $agent = new CompanyQualifier($targetProfile->project);

        if ($agentRun !== null) {
            $agent->recordInto($agentRun);
        }

        $criteria = (string) json_encode($targetProfile->criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        /** @var StructuredAgentResponse $verdict */
        $verdict = $agent->prompt(
            "Target profile [{$targetProfile->name}]:\n{$criteria}\n\n"
            ."Company website ({$candidate->website}):\n".mb_substr($parsed->text, 0, 8_000),
        );

        if (! ($verdict['is_a_prospect'] ?? false)) {
            return false;
        }

        $this->store($targetProfile, $run, $candidate, $parsed, $verdict->structured);

        return true;
    }

    /**
     * Already found for this project, so not worth a page fetch or a model call
     * a second time.
     */
    public function alreadyKnown(TargetProfile $targetProfile, Candidate $candidate): bool
    {
        $domain = $candidate->domain();

        return $domain !== null && Company::query()
            ->where('project_id', $targetProfile->project_id)
            ->where('domain', $domain)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $verdict
     */
    private function store(TargetProfile $targetProfile, DiscoveryRun $run, Candidate $candidate, ParsedPage $page, array $verdict): void
    {
        $company = Company::updateOrCreate(
            ['project_id' => $targetProfile->project_id, 'domain' => (string) $candidate->domain()],
            [
                'name' => $verdict['company_name'] ?: $candidate->name,
                'website' => $candidate->website,
                'industry' => $verdict['industry'] ?? null,
                'size' => $verdict['size'] ?? null,
                'location' => $verdict['location'] ?? null,
                // Detected here, not per project: Belgium runs FR, NL and EN in
                // one city, and this drives the language of the email.
                'language' => $page->language ?? mb_substr((string) ($verdict['language'] ?? ''), 0, 2) ?: null,
                'facts' => $candidate->facts,
                'source' => $candidate->source,
                'source_url' => $candidate->sourceUrl,
                'discovered_at' => now(),
            ],
        );

        CompanyTargetEvaluation::updateOrCreate(
            ['company_id' => $company->id, 'target_profile_id' => $targetProfile->id],
            [
                'discovery_run_id' => $run->id,
                'fit_score' => (int) ($verdict['fit_score'] ?? 0),
                'fit_reason' => (string) ($verdict['fit_reason'] ?? ''),
            ],
        );
    }
}
