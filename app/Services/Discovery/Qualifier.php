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
        $parsed = null;

        // A business with no site of its own is judged on what the directory
        // published about it. Thinner evidence, and the score reflects that —
        // but a chip shop with an address and no website is still a prospect,
        // and it is the one nobody else is calling.
        if ($candidate->website !== null) {
            $page = $this->fetcher->fetch($candidate->website);

            if ($page === null) {
                return false;
            }

            $parsed = $this->html->parse((string) $page->content, $candidate->website);

            if ($parsed->isEmpty()) {
                return false;
            }
        }

        $agent = new CompanyQualifier($targetProfile->project);

        if ($agentRun !== null) {
            $agent->recordInto($agentRun);
        }

        $criteria = (string) json_encode($targetProfile->criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        /** @var StructuredAgentResponse $verdict */
        $verdict = $agent->prompt(
            "Target profile [{$targetProfile->name}]:\n{$criteria}\n\n".$this->evidence($candidate, $parsed),
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
        return self::existing($targetProfile->project_id, $candidate) !== null;
    }

    /**
     * The row this candidate would land on. The domain is the key wherever
     * there is one; with no site the name is all every source agrees on, so
     * two same-named businesses in different towns are treated as one — losing
     * a prospect costs less than writing to the same person twice.
     */
    public static function existing(int $projectId, Candidate $candidate): ?Company
    {
        $domain = $candidate->domain();

        $query = Company::query()->where('project_id', $projectId);

        return $domain !== null
            ? $query->where('domain', $domain)->first()
            : $query->whereNull('domain')->whereRaw('lower(name) = ?', [mb_strtolower($candidate->name)])->first();
    }

    /**
     * What the model is asked to judge: the company's own pages when it has
     * them, and otherwise the line a directory published about it.
     */
    private function evidence(Candidate $candidate, ?ParsedPage $page): string
    {
        if ($page !== null) {
            return "Company website ({$candidate->website}):\n".mb_substr($page->text, 0, 8_000);
        }

        $facts = (string) json_encode($candidate->facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "This business publishes no website. All that is known is what a directory listed:\n"
            ."Name: {$candidate->name}\n{$facts}";
    }

    /**
     * @param  array<string, mixed>  $verdict
     */
    private function store(TargetProfile $targetProfile, DiscoveryRun $run, Candidate $candidate, ?ParsedPage $page, array $verdict): void
    {
        $company = self::existing($targetProfile->project_id, $candidate)
            ?? new Company(['project_id' => $targetProfile->project_id, 'domain' => $candidate->domain()]);

        $language = $page?->language;

        $company->fill([
            // The model's reading of the name wins only where it read a
            // site: with no site the directory's spelling IS the dedupe
            // key, and a rewritten name would insert a second row on the
            // next run rather than find this one.
            'name' => ($page !== null ? $verdict['company_name'] : null) ?: $candidate->name,
            'website' => $candidate->website,
            'industry' => $verdict['industry'] ?? null,
            'size' => $verdict['size'] ?? null,
            'location' => $verdict['location'] ?? null,
            // Detected here, not per project: Belgium runs FR, NL and EN in
            // one city, and this drives the language of the email.
            'language' => $language ?? mb_substr((string) ($verdict['language'] ?? ''), 0, 2) ?: null,
            'facts' => $candidate->facts,
            'source' => $candidate->source,
            'source_url' => $candidate->sourceUrl,
            'discovered_at' => now(),
        ])->save();

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
