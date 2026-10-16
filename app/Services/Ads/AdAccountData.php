<?php

namespace App\Services\Ads;

final readonly class AdAccountData
{
    public function __construct(
        public string $externalId,
        public string $name,
        public string $currency,
        public ?string $timeZone,
    ) {}
}
