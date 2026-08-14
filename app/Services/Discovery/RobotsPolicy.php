<?php

namespace App\Services\Discovery;

use App\Support\Url;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * robots.txt is respected, per the crawl constraints in the spec — both because
 * it is the honest thing to do and because getting our IP ranges blacklisted
 * would end lead discovery.
 *
 * ponytail: prefix matching only, no wildcards or Allow overrides. That covers
 * the overwhelming majority of real robots.txt files; revisit if we hit a site
 * that needs the full grammar.
 */
class RobotsPolicy
{
    /** @var array<string, array<int, string>> */
    private array $disallowed = [];

    public function allows(string $url): bool
    {
        $host = Url::host($url);

        if ($host === null) {
            return false;
        }

        foreach ($this->rulesFor($url, $host) as $rule) {
            if (str_starts_with(Url::path($url), $rule)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function rulesFor(string $url, string $host): array
    {
        return $this->disallowed[$host] ??= $this->fetch($url);
    }

    /**
     * @return array<int, string>
     */
    private function fetch(string $url): array
    {
        $parts = parse_url($url);
        $robots = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').'/robots.txt';

        try {
            $response = Http::withHeaders(['User-Agent' => config('eveil.crawl.user_agent')])
                ->timeout(10)
                ->get($robots);
        } catch (Throwable) {
            // A site that cannot serve robots.txt has not forbidden anything.
            return [];
        }

        return $response->successful() ? $this->parse($response->body()) : [];
    }

    /**
     * @return array<int, string>
     */
    private function parse(string $body): array
    {
        $rules = [];
        $applies = false;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map(trim(...), explode(':', $line, 2));
            $field = mb_strtolower($field);

            if ($field === 'user-agent') {
                $applies = in_array(mb_strtolower($value), ['*', 'eveilbot'], true);

                continue;
            }

            if ($applies && $field === 'disallow' && $value !== '') {
                $rules[] = $value;
            }
        }

        return $rules;
    }
}
