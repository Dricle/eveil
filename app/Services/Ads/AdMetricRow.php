<?php

namespace App\Services\Ads;

use Carbon\CarbonImmutable;

final readonly class AdMetricRow
{
    public function __construct(
        public string $adExternalId,
        public CarbonImmutable $date,
        public int $impressions,
        public int $clicks,
        public int $costMicros,
        public float $conversions,
        public float $conversionValue,
    ) {}
}
