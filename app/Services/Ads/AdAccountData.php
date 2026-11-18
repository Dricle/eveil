<?php

namespace App\Services\Ads;

final readonly class AdAccountData
{
    /**
     * @param  array<string, string>  $ref  what the driver needs again about the account, stored as is
     * @param  list<array{id: string, name: string}>  $identities  who the ads can be posted as, on networks that ask
     */
    public function __construct(
        public string $externalId,
        public string $name,
        public string $currency,
        public ?string $timeZone,
        public array $ref = [],
        public array $identities = [],
    ) {}
}
