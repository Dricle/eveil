<?php

namespace App\Services\Ads;

use App\Enums\AdVerdict;
use App\Support\Settings;

/**
 * A first read of one ad against its account, computed so the agent does not
 * have to: language models get ratios and confidence intervals wrong. The
 * verdict is a hint shown next to the numbers, never a rule; the agent may
 * see a trend or a context it misses and overrule it.
 *
 * The rate judged is the conversion rate when the account tracks conversions,
 * the click-through rate otherwise: the two goals an account can have.
 */
class AdClassifier
{
    /** 95% two-sided. */
    private const Z = 1.96;

    public function __construct(private Settings $settings) {}

    /**
     * `$trials` and `$successes` are impressions and clicks when judging the
     * click rate, clicks and conversions when judging the conversion rate.
     * Enough data is always measured in clicks and spend, whichever rate.
     */
    public function verdict(int $trials, int|float $successes, int $clicks, int $costMicros, float $baselineRate): AdVerdict
    {
        $hasSpent = $costMicros >= $this->settings->int('ads.policy.min_spend_micros');
        $hasClicks = $clicks >= $this->settings->int('ads.policy.min_clicks');

        if (! $hasSpent && ! $hasClicks) {
            return AdVerdict::Learning;
        }

        [$low, $high] = self::interval($trials, $successes);

        return match (true) {
            $low > $baselineRate => AdVerdict::Winner,
            $high < $baselineRate => AdVerdict::Loser,
            default => AdVerdict::Flat,
        };
    }

    /**
     * Wilson score interval for a rate, which unlike the textbook one stays
     * sane on small counts and on rates near zero, which is where ads live.
     *
     * @return array{0: float, 1: float}
     */
    public static function interval(int $trials, int|float $successes): array
    {
        if ($trials === 0) {
            return [0.0, 1.0];
        }

        $rate = min(1.0, $successes / $trials);
        $z2 = self::Z ** 2;
        $denominator = 1 + $z2 / $trials;
        $centre = ($rate + $z2 / (2 * $trials)) / $denominator;
        $margin = self::Z * sqrt($rate * (1 - $rate) / $trials + $z2 / (4 * $trials ** 2)) / $denominator;

        return [max(0.0, $centre - $margin), min(1.0, $centre + $margin)];
    }
}
