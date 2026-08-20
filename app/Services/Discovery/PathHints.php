<?php

namespace App\Services\Discovery;

use App\Ai\Agents\ContactPageFinder;
use App\Enums\PathHintKind;
use App\Models\PathHint;
use App\Models\Project;
use App\Support\ParsedPage;
use App\Support\Url;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Which of a site's links are worth fetching, from fragments the app learned
 * itself. There is no list anywhere: not in a const, not in a seeder.
 *
 * The first version was a const covering four languages, which silently missed
 * `/contacto`, `/chi-siamo`, `/om-oss`, `/kontakty` and every market nobody had
 * thought of. Moving it to a seeder was the same list in a different file.
 * `path_hints` starts EMPTY: the first site asks a model, the answer is written
 * back, and by a handful of sites in the common words are all there. A cold
 * start costs a few tenths of a cent, once, for the whole instance.
 *
 * Full lifecycle, no human curation required:
 *   create. The model picks links, `learn()` turns paths into fragments
 *   rank: `matched`/`hits` float what works to the top
 *   retire. A fragment that keeps choosing pages and never delivering is
 *             deleted by `review()`; it was spending a fetch every time
 *
 * `is_locked` is the human override, for the one row somebody wants kept or
 * killed regardless.
 */
class PathHints
{
    /** @return Collection<int, string> URLs worth fetching, best first */
    public function pick(ParsedPage $home, PathHintKind $kind, Project $project, int $limit): Collection
    {
        $sameHost = collect($home->links)
            ->filter(fn (string $url): bool => Url::host($url) === Url::host($home->url))
            ->unique()
            ->values();

        $matched = $this->matching($sameHost, $kind)->take($limit);

        if ($matched->isNotEmpty()) {
            return $matched;
        }

        // Nothing matched: either the site is in a language we have not met, or
        // it words things unusually. Ask, then remember, so the next site like
        // it costs nothing.
        return $this->ask($home, $kind, $project)->take($limit);
    }

    /**
     * Records how a chosen page turned out.
     *
     * Both halves matter. `matched` alone says a fragment is popular; `hits`
     * alone says nothing about the fetches it wasted. The ratio is what lets
     * `review()` delete a bad fragment without anyone reading the list.
     */
    public function record(string $url, PathHintKind $kind, bool $paidOff): void
    {
        foreach ($this->hintsMatching($url, $kind) as $hint) {
            $hint->increment('matched');

            if ($paidOff) {
                $hint->increment('hits');
            }
        }
    }

    /**
     * Deletes the fragments that cost more than they return.
     *
     * The guard against `learn()` picking up something far too generic: a
     * model that answers `/informations` once writes a fragment that then
     * selects a page on half the sites on the instance, forever. Nothing here
     * needs a stop-list of banned words, which would be another hardcoded list:
     * a fragment that is too generic simply fails to deliver, and the ratio
     * catches it.
     *
     * @return array<int, string> what was retired, for the operator to see
     */
    public function review(PathHintKind $kind): array
    {
        $dead = PathHint::query()
            ->where('kind', $kind)
            ->get()
            ->filter(fn (PathHint $hint): bool => $hint->isDeadWeight());

        $dead->each(fn (PathHint $hint) => $hint->delete());

        return $dead->pluck('token')->all();
    }

    /**
     * @return Collection<int, PathHint>
     */
    private function hintsMatching(string $url, PathHintKind $kind): Collection
    {
        $path = mb_strtolower(Url::path($url));

        return PathHint::query()
            ->where('kind', $kind)
            ->get()
            ->filter(fn (PathHint $hint): bool => str_contains($path, $hint->token))
            ->values();
    }

    /**
     * Hits first, so the fragment that keeps working is tried before the one
     * that worked once.
     *
     * @param  Collection<int, string>  $urls
     * @return Collection<int, string>
     */
    private function matching(Collection $urls, PathHintKind $kind): Collection
    {
        /** @var array<int, string> $tokens */
        $tokens = PathHint::query()->where('kind', $kind)->orderByDesc('hits')->pluck('token')->all();

        $matched = [];

        foreach ($tokens as $token) {
            foreach ($urls as $url) {
                if (str_contains(mb_strtolower(Url::path($url)), $token)) {
                    $matched[$url] = true;
                }
            }
        }

        /** @var Collection<int, string> */
        return new Collection(array_keys($matched));
    }

    /**
     * @return Collection<int, string>
     */
    private function ask(ParsedPage $home, PathHintKind $kind, Project $project): Collection
    {
        if ($home->isEmpty()) {
            return new Collection;
        }

        try {
            $response = (new ContactPageFinder($project))->prompt(
                "Home page: {$home->url}\n\n".mb_substr($home->text, 0, 12_000),
            );
        } catch (Throwable) {
            // A site we cannot read must not cost the run everything else.
            return new Collection;
        }

        /** @var array<int, array{url?: string}> $links */
        $links = $response->structured['links'] ?? [];

        $picked = [];

        foreach ($links as $link) {
            $url = Url::resolve((string) ($link['url'] ?? ''), $home->url);

            // A model asked for links on this site will occasionally hand back
            // someone else's.
            if ($url === null || Url::host($url) !== Url::host($home->url)) {
                continue;
            }

            $picked[$url] = true;
            $this->learn($url, $kind);
        }

        /** @var Collection<int, string> */
        return new Collection(array_keys($picked));
    }

    /**
     * Turns a path the model picked into a fragment for next time.
     *
     * The last meaningful segment, lowercased: `/nl/over-ons` teaches `over-ons`
     * rather than the whole path, which would only ever match that one site.
     * Numeric and very short segments teach nothing and are skipped.
     */
    private function learn(string $url, PathHintKind $kind): void
    {
        $segments = array_values(array_filter(explode('/', mb_strtolower(Url::path($url)))));
        $token = end($segments);

        if ($token === false || mb_strlen($token) < 4 || is_numeric($token)) {
            return;
        }

        // Strip an extension so `/contacto.html` teaches `contacto`.
        $token = (string) preg_replace('/\.(html?|php|aspx?)$/', '', $token);

        PathHint::query()->firstOrCreate(['kind' => $kind, 'token' => $token]);
    }
}
