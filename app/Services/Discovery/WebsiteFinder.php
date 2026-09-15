<?php

namespace App\Services\Discovery;

use App\Enums\HostKind;
use App\Models\Project;
use App\Services\Discovery\Sources\WebSearchSource;
use App\Support\HtmlText;
use App\Support\Url;

/**
 * One search-engine shot at a company's own site, for a company a source
 * found with no site of its own - a registry record, chiefly, which publishes
 * a legal name and an address and never a website.
 *
 * A search for "name" + address just as often turns up the directory or blog
 * post the address came from in the first place, which would name the
 * company too - `str_contains` alone cannot tell the two apart. `HostRegistry`
 * can: the same model-backed classifier discovery already uses to tell a
 * company's own site from an index is asked here first, so a directory or
 * social host is never even considered a candidate. Only a host judged
 * `Entity` is then checked for the name, and only that combination is
 * trusted: a wrong site would mean emailing a stranger about somebody else's
 * fit score.
 */
class WebsiteFinder
{
    public function __construct(
        private WebSearchSource $search,
        private HostRegistry $hosts,
        private PageFetcher $fetcher,
        private HtmlText $html,
    ) {}

    public function find(string $name, string $address, Project $project): ?string
    {
        $found = $this->search->search(['query' => "\"{$name}\" {$address}"]);

        if ($found->isEmpty()) {
            return null;
        }

        $kinds = $this->hosts->classify(
            $found->map(fn (Candidate $candidate): string => (string) $candidate->website),
            $project,
        );

        foreach ($found as $candidate) {
            $url = $candidate->website;
            $host = $url === null ? null : Url::host($url);

            if ($host === null || ($kinds[$host] ?? HostKind::Entity) !== HostKind::Entity) {
                continue;
            }

            if ($this->confirms($url, $name)) {
                return $url;
            }
        }

        return null;
    }

    private function confirms(string $url, string $name): bool
    {
        $page = $this->fetcher->fetch($url);

        if ($page === null) {
            return false;
        }

        $parsed = $this->html->parse((string) $page->content, $url);

        return ! $parsed->isEmpty() && str_contains(mb_strtolower($parsed->text), mb_strtolower($name));
    }
}
