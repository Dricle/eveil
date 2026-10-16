<?php

namespace App\Services\Ads;

/**
 * What creating a campaign left on the network: its id, the ids of the pieces
 * the driver needs again later (`$ref`, stored as is), and each ad's id keyed
 * by our ad id.
 */
final readonly class LaunchedCampaign
{
    /**
     * @param  array<string, string>  $ref
     * @param  array<int, string>  $adExternalIds
     */
    public function __construct(
        public string $externalId,
        public array $ref,
        public array $adExternalIds,
    ) {}
}
