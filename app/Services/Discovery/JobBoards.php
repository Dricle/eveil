<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The roles a company is hiring for, read from the job board it links to.
 *
 * A company hiring an office manager or a first salesperson is telling you
 * where it hurts, and that is the sharpest opener a cold mail can have. These
 * boards publish each customer's open roles as free, unauthenticated JSON (the
 * same feed their hosted page renders), so one request answers it.
 *
 * The board is only ever the one the company's OWN site links to, never a
 * guessed token: a guessed `acme` that exists is somebody else's board, and
 * its roles would be pinned on the wrong company.
 *
 * The vendor list is a set of public API formats, not a guess about the
 * world: a board missing from it costs a signal, never a bad send.
 */
class JobBoards
{
    /** Roles past this say nothing more, and only fatten the prompt. */
    private const MAX_ROLES = 10;

    /**
     * Link pattern => [feed URL with `{token}`, JSON path to the list, title key].
     *
     * @var array<string, array{0: string, 1: string|null, 2: string}>
     */
    private const BOARDS = [
        '~^https?://(?:job-)?boards\.greenhouse\.io/([a-z0-9_-]+)~i' => ['https://boards-api.greenhouse.io/v1/boards/{token}/jobs', 'jobs', 'title'],
        '~^https?://jobs\.lever\.co/([a-z0-9_-]+)~i' => ['https://api.lever.co/v0/postings/{token}?mode=json', null, 'text'],
        '~^https?://jobs\.ashbyhq\.com/([a-z0-9_.-]+)~i' => ['https://api.ashbyhq.com/posting-api/job-board/{token}', 'jobs', 'title'],
        '~^https?://apply\.workable\.com/([a-z0-9_-]+)~i' => ['https://apply.workable.com/api/v1/widget/accounts/{token}', 'jobs', 'title'],
        '~^https?://([a-z0-9-]+)\.recruitee\.com~i' => ['https://{token}.recruitee.com/api/offers/', 'offers', 'title'],
    ];

    /**
     * Open role titles from the first board these links point at, or an empty
     * list when there is none or it would not answer.
     *
     * @param  array<int, string>  $links
     * @return list<string>
     */
    public function openRoles(array $links): array
    {
        foreach ($links as $link) {
            foreach (self::BOARDS as $pattern => [$feed, $path, $titleKey]) {
                if (preg_match($pattern, $link, $match) && ! in_array(mb_strtolower($match[1]), ['embed', 'api', 'www'], true)) {
                    return $this->fetch(str_replace('{token}', $match[1], $feed), $path, $titleKey);
                }
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function fetch(string $url, ?string $path, string $titleKey): array
    {
        try {
            $response = Http::timeout((int) config('eveil.crawl.timeout'))->acceptJson()->get($url);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $postings = $path === null ? $response->json() : $response->json($path);

        return collect(is_array($postings) ? $postings : [])
            ->map(fn (mixed $posting): string => mb_trim((string) (is_array($posting) ? ($posting[$titleKey] ?? '') : '')))
            ->filter()
            ->unique()
            ->take(self::MAX_ROLES)
            ->values()
            ->all();
    }
}
