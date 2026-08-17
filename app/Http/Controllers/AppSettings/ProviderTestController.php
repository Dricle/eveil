<?php

namespace App\Http\Controllers\AppSettings;

use App\Ai\AgentSettings;
use App\Ai\ProviderCredentials;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laravel\Ai\AnonymousAgent;
use Throwable;

/**
 * Sends the smallest possible prompt through a provider and reports what came
 * back.
 *
 * The point is the failure, not the success: a key rejected by the provider, a
 * model name that does not exist, an unreachable local endpoint. Without this
 * the first sign of a bad key is a discovery run dying in a queue an hour later.
 */
class ProviderTestController extends Controller
{
    public function store(string $provider, AgentSettings $agents, ProviderCredentials $credentials): RedirectResponse
    {
        $credentials->apply();

        // Whatever an agent on this provider would ask for, so the check covers
        // the model name too and not merely the credentials.
        $model = collect($agents->known())
            ->first(fn (string $agent): bool => $agents->providerName($agent) === $provider);

        try {
            $response = (new AnonymousAgent('Reply with the single word OK.', [], []))
                ->prompt('ping', provider: $provider, model: $model === null ? null : $agents->model($model), timeout: 30);
        } catch (Throwable $e) {
            return back()->withErrors(['key' => $provider.' refused the call: '.$e->getMessage()]);
        }

        return back()->with('status', $provider.' answered: '.trim(mb_substr($response->text ?? '', 0, 120)));
    }
}
