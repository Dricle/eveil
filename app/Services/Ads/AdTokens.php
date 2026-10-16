<?php

namespace App\Services\Ads;

use Carbon\CarbonImmutable;

final readonly class AdTokens
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public ?CarbonImmutable $expiresAt,
    ) {}
}
