<?php

namespace App\Ai;

use Laravel\Ai\Responses\Data\Usage;

/**
 * List-price estimate in US dollars, from `config('eveil.pricing')`.
 *
 * Cache reads bill at a tenth of the input rate and cache writes at 1.25x. An
 * unpriced model costs 0 rather than throwing: a missing rate must never break
 * a run, and the zero is visible in `agent_runs`.
 */
class ModelPricing
{
    public function costOf(string $model, Usage $usage): float
    {
        $rates = $this->ratesFor($model);

        if ($rates === null) {
            return 0.0;
        }

        return round(
            ($usage->promptTokens * $rates['input']
                + $usage->cacheReadInputTokens * $rates['input'] * 0.1
                + $usage->cacheWriteInputTokens * $rates['input'] * 1.25
                + $usage->completionTokens * $rates['output']
            ) / 1_000_000,
            6,
        );
    }

    /**
     * Providers answer with a dated model id — `claude-haiku-4-5-20251001` for a
     * `claude-haiku-4-5` request — so an exact lookup silently prices a real
     * call at zero. The longest matching prefix wins.
     *
     * @return array{input: float, output: float}|null
     */
    private function ratesFor(string $model): ?array
    {
        /** @var array<string, array{input: float, output: float}> $pricing */
        $pricing = config('eveil.pricing', []);

        if (isset($pricing[$model])) {
            return $pricing[$model];
        }

        $match = null;

        foreach ($pricing as $key => $rates) {
            if (str_starts_with($model, $key) && ($match === null || mb_strlen($key) > mb_strlen($match))) {
                $match = $key;
            }
        }

        return $match === null ? null : $pricing[$match];
    }

    /**
     * Cached tokens still crossed the wire, so the meter counts them as input.
     */
    public function inputTokens(Usage $usage): int
    {
        return $usage->promptTokens + $usage->cacheReadInputTokens + $usage->cacheWriteInputTokens;
    }
}
