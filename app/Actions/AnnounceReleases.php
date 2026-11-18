<?php

namespace App\Actions;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostFrequency;
use App\Jobs\GenerateSocialPost;
use App\Models\CodeRepository;
use App\Services\RepoReader;
use Illuminate\Support\Str;

/**
 * A new GitHub release on a linked repo becomes a "we just shipped" draft on
 * every network the project posts on, written from its release notes exactly
 * as if the user had told Evie about it. A draft only: nothing is published,
 * and the user can ask for a video on it like any other.
 *
 * The first check of a repo records its latest release without announcing
 * it, so linking a repo never posts about old news.
 */
class AnnounceReleases
{
    public function __construct(private RepoReader $reader) {}

    /**
     * How many drafts were queued.
     */
    public function handle(): int
    {
        $queued = 0;

        CodeRepository::query()->with('project')->each(function (CodeRepository $codeRepository) use (&$queued): void {
            $release = $this->reader->latestRelease($codeRepository->url, $codeRepository->project->github_token);

            if ($release === null || $release['id'] === $codeRepository->last_release_id) {
                return;
            }

            $isFirstCheck = $codeRepository->last_release_id === null;

            $codeRepository->update(['last_release_id' => $release['id']]);

            if ($isFirstCheck) {
                return;
            }

            $brief = "We just shipped {$release['name']} ({$codeRepository->name}). Write about what changed for the people using the product, not about the code. Release notes:\n\n"
                .Str::limit($release['notes'], 4000)
                ."\n\n{$release['url']}";

            foreach ($this->platformsOf($codeRepository) as $platform) {
                GenerateSocialPost::dispatch($codeRepository->project, $platform, $brief);
                $queued++;
            }
        });

        return $queued;
    }

    /**
     * The networks the project posts on: a cadence set and, where Eveil
     * publishes, a working account, as for the regular cadence.
     *
     * @return array<int, SocialPlatform>
     */
    private function platformsOf(CodeRepository $codeRepository): array
    {
        $project = $codeRepository->project;

        return array_values(array_filter(
            SocialPlatform::cases(),
            fn (SocialPlatform $platform): bool => $project->getAttribute($platform->frequencyColumn()) !== SocialPostFrequency::Off
                && (! $platform->publishesThroughApi() || $project->socialAccounts()
                    ->where('platform', $platform)
                    ->where('status', SocialAccountStatus::Active)
                    ->exists()),
        ));
    }
}
