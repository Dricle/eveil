<?php

namespace App\Cloud\Ai;

use App\Ai\Contracts\SpendGuardInterface;
use App\Cloud\Actions\AutoTopUp;
use App\Cloud\Models\CreditPrice;
use App\Cloud\Models\CreditTransaction;
use App\Models\Project;

/**
 * Cloud's answer to `SpendGuardInterface`: a real balance on the
 * organization, checked before the provider is called and debited only
 * after it answers. Every other call site in the app is unaware this
 * exists — `RecordsAgentRun` calls the interface, not this class.
 */
class CreditSpendGuard implements SpendGuardInterface
{
    public function __construct(private AutoTopUp $autoTopUp) {}

    public function refusal(Project $project, string $agent): ?string
    {
        // The operator's own organization: dogfooding the product must not
        // cost the operator credits, in cloud or anywhere else.
        if ($project->organization->ownedBySuperAdmin()) {
            return null;
        }

        $price = CreditPrice::current($agent);

        if ($price === null) {
            // Never a silent free ride: an agent nobody priced is a config
            // bug, not a discount.
            return "No credit price is set for {$agent}. An operator needs to add one before this can run.";
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

        $price = CreditPrice::current($agent);

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
            'agent' => $agent,
            'agent_run_id' => $agentRunId,
        ]);

        $this->autoTopUp->maybeTrigger($project->organization);
    }
}
