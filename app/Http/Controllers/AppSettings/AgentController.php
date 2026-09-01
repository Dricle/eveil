<?php

namespace App\Http\Controllers\AppSettings;

use App\Actions\SwitchAgentProvider;
use App\Ai\AgentSettings;
use App\Ai\ModelCatalogue;
use App\Ai\ProviderCredentials;
use App\Cloud\Models\CreditPrice;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\AgentSettingRequest;
use App\Http\Requests\AppSettings\ProviderSwitchRequest;
use App\Models\AgentRun;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Enums\Lab;

/**
 * One line per agent: which provider and model it runs on, how long it may
 * take, and what it has cost so far.
 *
 * The list comes from the code, so adding an agent class adds a line here with
 * nothing to register. A fresh install works without opening this screen: the
 * defaults ship in a migration, and the command line does the same job over
 * SSH.
 */
class AgentController extends Controller
{
    public function index(AgentSettings $agents, ProviderCredentials $credentials, ModelCatalogue $models): Response
    {
        // Averages computed in SQL, not `sum / calls` in PHP: a zero-call
        // agent would divide by zero, and the database already has to do
        // the grouping regardless.
        $spend = AgentRun::query()
            ->selectRaw(
                'agent, count(*) as calls, sum(tokens_in) as tokens_in, sum(tokens_out) as tokens_out, '
                .'round(avg(tokens_in)) as avg_tokens_in, round(avg(tokens_out)) as avg_tokens_out'
            )
            ->groupBy('agent')
            ->get()
            ->keyBy('agent');

        return Inertia::render('app-settings/Agents', [
            'agents' => collect($agents->classes())
                ->map(fn (string $class, string $slug): array => [
                    'slug' => $slug,
                    'provider' => $agents->providerName($slug),
                    'model' => $agents->model($slug),
                    'timeout' => $agents->timeout($slug),
                    'overridden' => $agents->isOverridden($slug),
                    'strict' => $class::requiresStrictStructure(),
                    'smallModelOk' => $class::smallModelSufficient(),
                    'calls' => (int) ($spend[$slug]->calls ?? 0),
                    'tokens_in' => (int) ($spend[$slug]->tokens_in ?? 0),
                    'tokens_out' => (int) ($spend[$slug]->tokens_out ?? 0),
                    // Per call, not just the running total: a superadmin
                    // calibrating `credit_prices` needs "what does ONE call
                    // cost", the same shape as the grid itself.
                    'avg_tokens_in' => (int) ($spend[$slug]->avg_tokens_in ?? 0),
                    'avg_tokens_out' => (int) ($spend[$slug]->avg_tokens_out ?? 0),
                    // Cloud's calibration target. Present on every edition -
                    // the row is harmless where nothing reads it - so the
                    // screen never needs an edition check to decide whether
                    // the column exists, only whether to show it.
                    'credit_price' => CreditPrice::current($slug),
                ])
                ->values()
                ->all(),
            'labs' => collect(Lab::cases())->map(fn (Lab $lab): string => $lab->value)->all(),
            // What each provider names for itself. Its default, cheapest and
            // smartest text model. Suggestions, never the allowed set: nobody
            // publishes a list of model ids, and a fixed one would block the
            // model released this morning.
            'models' => $models->suggestions(),
            // Which providers can actually be called, so a line pointing at one
            // with no key says so here rather than in a job an hour later.
            'configured' => collect(Lab::cases())
                ->mapWithKeys(fn (Lab $lab): array => [$lab->value => $credentials->isConfigured($lab->value)])
                ->all(),
        ]);
    }

    public function update(AgentSettingRequest $request, string $agent, AgentSettings $agents): RedirectResponse
    {
        abort_unless(in_array($agent, $agents->known(), true), 404);

        $agents->save($agent, $request->validated());

        // The credit grid is calibrated on a specific model mix, so in cloud
        // this and the prices are one operation, never two.
        return to_route('app-settings.agents.index')
            ->with('status', $agent.' saved. In cloud, adjust credit_prices in the same move.');
    }

    /**
     * Every agent onto one provider, which is the first move for anybody not
     * using the one that ships. Changing eight lines by hand, each needing a
     * model id looked up elsewhere, is where a setup screen loses people.
     */
    public function switchProvider(
        ProviderSwitchRequest $request,
        SwitchAgentProvider $switch,
        ProviderCredentials $credentials,
    ): RedirectResponse {
        $provider = $request->string('provider')->value();

        // Refused rather than saved: a mapping pointing at a provider with no
        // key looks configured on this screen and fails in a job an hour later.
        abort_unless($credentials->isConfigured($provider), 422);

        $moved = $switch->handle($provider);

        return to_route('app-settings.agents.index')->with(
            'status',
            "{$moved} agents moved to {$provider}, each keeping its timeout and landing on that "
            .'provider\'s equivalent model. Change any of them below. In cloud, adjust credit_prices in the same move.',
        );
    }

    public function destroy(string $agent, AgentSettings $agents): RedirectResponse
    {
        abort_unless(in_array($agent, $agents->known(), true), 404);

        $agents->reset($agent);

        return to_route('app-settings.agents.index')
            ->with('status', $agent.' dropped back to the default mapping.');
    }
}
