<?php

namespace App\Services\Social;

use App\Models\SocialAccount;

/**
 * X's driver, deliberately inert: X's API is paid per call, so Eveil neither
 * publishes (the user posts by hand, see `MarkSocialPostPublished`) nor reads
 * numbers there. The day X's API is wanted, it goes here and nothing else
 * changes.
 */
class XClient implements SocialClientInterface
{
    public function publish(SocialAccount $account, string $text, ?string $language = null): ?array
    {
        return null;
    }

    public function likeCounts(array $ids): array
    {
        return [];
    }
}
