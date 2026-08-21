<?php

namespace App\Actions;

use App\Ai\AgentSettings;
use App\Ai\ModelCatalogue;

/**
 * Moving every agent to one provider, which is the first thing anybody who does
 * not use the shipped one has to do.
 *
 * Eight lines changed one at a time, each needing a model id looked up
 * somewhere else, is the shape of a setup screen people abandon. Nothing here
 * is unavailable per agent afterwards: this is the first move, not the only
 * one.
 */
class SwitchAgentProvider
{
    public function __construct(private AgentSettings $agents, private ModelCatalogue $models) {}

    /**
     * @return int how many agents moved
     */
    public function handle(string $provider): int
    {
        // What the provider names for itself: default, cheapest, smartest. An
        // empty list is a provider that publishes none, and then every agent
        // gets no model at all, which is how `laravel/ai` is told to use the
        // provider's own default.
        [$default, $cheapest, $smartest] = array_pad($this->models->for($provider), 3, null);

        $moved = 0;

        foreach ($this->agents->known() as $agent) {
            $this->agents->switchProvider($agent, $provider, $this->modelFor($agent, $default, $cheapest, $smartest));
            $moved++;
        }

        return $moved;
    }

    /**
     * The same TIER on the new provider, not the same model id.
     *
     * An agent on its current provider's smartest model is there because it
     * writes prose or plans a run; one on the cheapest reads a page and returns
     * fields. That distinction is the operator's, whether they made it or the
     * install shipped it, and it is worth more than any rule this class could
     * invent. Where the current model matches neither, the provider's own
     * default is the honest answer rather than a guess.
     */
    private function modelFor(string $agent, ?string $default, ?string $cheapest, ?string $smartest): ?string
    {
        $current = $this->agents->model($agent);

        // Nothing configured means it was already on its provider's own
        // default, and it stays on the new one's. Without this the match below
        // would compare null against a null catalogue entry and land there by
        // accident rather than by decision.
        if ($current === null) {
            return $default;
        }

        $before = $this->models->for($this->agents->providerName($agent));

        return match ($current) {
            $before[2] ?? null => $smartest ?? $default,
            $before[1] ?? null => $cheapest ?? $default,
            default => $default,
        };
    }
}
