<?php

namespace App\Cloud\Ai;

use App\Ai\Agents\Evie;
use App\Ai\Contracts\SpendGuardInterface;
use App\Cloud\Actions\AutoTopUp;
use App\Cloud\Models\CreditPrice;
use App\Cloud\Models\CreditTransaction;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Cloud's answer to `SpendGuardInterface`: a real balance on the
 * organization, checked before the provider is called and debited only
 * after it answers. Every other call site in the app is unaware this
 * exists - `RecordsAgentRun` calls the interface, not this class.
 */
class CreditSpendGuard implements SpendGuardInterface
{
    /**
     * Tunable, not measured yet: the point at which Evie's resent history
     * starts costing meaningfully more in provider tokens. Lives here, not
     * on the agent itself, because tiered pricing is a cloud-only concern:
     * self-hosted is unmetered and has no reason to know this exists.
     */
    private const EVIE_TIER_1_MAX_TURN = 5;

    private const EVIE_TIER_2_MAX_TURN = 15;

    public function __construct(private AutoTopUp $autoTopUp) {}

    public function refusal(Project $project, string $agent): ?string
    {
        // The operator's own organization: dogfooding the product must not
        // cost the operator credits, in cloud or anywhere else.
        if ($project->organization->ownedBySuperAdmin()) {
            return null;
        }

        $pricingKey = $this->pricingKey($project, $agent);
        $price = CreditPrice::current($pricingKey);

        if ($price === null) {
            // Never a silent free ride: an agent nobody priced is a config
            // bug, not a discount.
            return "No credit price is set for {$pricingKey}. An operator needs to add one before this can run.";
        }

        if ($project->organization->credits_balance < $price) {
            return 'This project has no credits left. Top up to keep the searches running.';
        }

        return null;
    }

    public function charge(Project $project, string $agent, int $agentRunId): void
    {
        if ($project->organization->ownedBySuperAdmin()) {
            return;
        }

        $pricingKey = $this->pricingKey($project, $agent);
        $price = CreditPrice::current($pricingKey);

        if ($price === null) {
            return;
        }

        // `refusal()` already checked the balance moments ago; an
        // organization that has since been drained by a concurrent call is
        // the one race this does not chase further; see `Organization::debit()`.
        if (! $project->organization->debit($price)) {
            return;
        }

        CreditTransaction::create([
            'organization_id' => $project->organization_id,
            'type' => 'debit',
            'credits' => -$price,
            'agent' => $pricingKey,
            'agent_run_id' => $agentRunId,
        ]);

        $this->autoTopUp->maybeTrigger($project->organization);
    }

    /**
     * Every agent prices flat, by its own slug, except Evie: her whole
     * conversation is resent every turn, so a long thread genuinely costs
     * more in provider tokens than a short one. `agent_runs.agent` still
     * records the plain slug (`RecordsAgentRun` never sees this key); only
     * the credit lookup and `credit_transactions.agent` see the tier.
     */
    private function pricingKey(Project $project, string $agent): string
    {
        if ($agent !== Evie::slug()) {
            return $agent;
        }

        return "{$agent}:tier-{$this->evieTier($project)}";
    }

    private function evieTier(Project $project): int
    {
        $turns = $this->priorEvieTurns($project);

        return match (true) {
            $turns < self::EVIE_TIER_1_MAX_TURN => 1,
            $turns < self::EVIE_TIER_2_MAX_TURN => 2,
            default => 3,
        };
    }

    /**
     * How many user turns Evie's current conversation with this project
     * already has. Mirrors the participant scoping `Evie::forParticipant()`
     * uses (the `Project` itself), so this stays correct without the agent
     * needing to expose anything about its own conversation state.
     */
    private function priorEvieTurns(Project $project): int
    {
        $conversationId = DB::table(config('ai.conversations.tables.conversations', 'agent_conversations'))
            ->where('participant_type', Project::class)
            ->where('participant_id', $project->id)
            ->orderByDesc('updated_at')
            ->value('id');

        if ($conversationId === null) {
            return 0;
        }

        return DB::table(config('ai.conversations.tables.messages', 'agent_conversation_messages'))
            ->where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->count();
    }
}
