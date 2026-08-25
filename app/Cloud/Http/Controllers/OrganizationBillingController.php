<?php

namespace App\Cloud\Http\Controllers;

use App\Cloud\Http\Resources\CreditTransactionResource;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\CurrentProject;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationBillingController extends Controller
{
    public function __construct(private CurrentProject $currentProject, private Settings $settings) {}

    public function edit(Request $request): Response
    {
        $organization = $this->currentProject->organization();

        return Inertia::render('settings/OrganizationBilling', [
            // Only ever a success/cancel echo from Stripe Checkout's redirect.
            'checkout' => $request->query('checkout'),
            'onTrial' => $organization->isOnTrial(),
            'balance' => $organization->credits_balance,
            'creditsPerDollar' => $this->settings->int('billing.credits_per_dollar'),
            'hasPaymentMethod' => $organization->hasDefaultPaymentMethod(),
            'autoTopup' => [
                'threshold' => $organization->auto_topup_threshold,
                'amountCents' => $organization->auto_topup_amount_cents,
            ],
            'transactions' => CreditTransactionResource::collection(
                $organization->creditTransactions()->latest('id')->limit(50)->get()
            ),
            'creditsByProject' => $this->creditsByProject($organization),
        ]);
    }

    /**
     * Every project of the org, spend-first, none dropped for having spent
     * nothing yet. A raw join rather than the `agent_runs`/`credit_transactions`
     * models: both carry a project-scoping global scope that would otherwise
     * silently narrow this to whichever project the request happens to be
     * currently viewing, when the whole point here is every one of them.
     *
     * @return array<int, array{id: int, name: string, credits: int}>
     */
    private function creditsByProject(Organization $organization): array
    {
        // A plain query builder, not the `Project` model: Eloquent would
        // hydrate rows carrying a `credits` column no model property
        // declares, which is exactly what a raw aggregate is.
        return DB::table('projects')
            ->where('projects.organization_id', $organization->id)
            ->leftJoin('agent_runs', 'agent_runs.project_id', '=', 'projects.id')
            ->leftJoin('credit_transactions', function ($join): void {
                $join->on('credit_transactions.agent_run_id', '=', 'agent_runs.id')
                    ->where('credit_transactions.type', 'debit');
            })
            ->selectRaw('projects.id as id, projects.name as name, coalesce(sum(-credit_transactions.credits), 0) as credits')
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc('credits')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'credits' => (int) $row->credits,
            ])
            ->all();
    }
}
