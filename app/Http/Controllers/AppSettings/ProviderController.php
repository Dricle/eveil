<?php

namespace App\Http\Controllers\AppSettings;

use App\Ai\AgentSettings;
use App\Ai\ProviderCredentials;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\ProviderKeyRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Enums\Lab;

/**
 * Which provider the instance calls, and with whose key.
 *
 * The key never travels back to the browser: the screen says whether one is
 * stored and where it came from, and saving replaces it. Which provider each
 * agent runs on is decided next door, per agent: this page only answers "can we
 * call it at all".
 */
class ProviderController extends Controller
{
    public function edit(AgentSettings $agents, ProviderCredentials $credentials): Response
    {
        $inUse = collect($agents->known())
            ->map(fn (string $agent): string => $agents->providerName($agent))
            ->unique();

        return Inertia::render('app-settings/Provider', [
            'providers' => $inUse
                ->merge(collect(Lab::cases())
                    ->map(fn (Lab $lab): string => $lab->value)
                    ->filter(fn (string $provider): bool => $credentials->isStored($provider)))
                ->unique()
                ->sort()
                ->values()
                ->map(fn (string $provider): array => [
                    'name' => $provider,
                    // Names only: the keys themselves never travel to the
                    // browser, and the array position is enough to delete one.
                    'keys' => collect($credentials->keys($provider))
                        ->map(fn (array $entry): string => $entry['name'])
                        ->all(),
                    'configured' => $credentials->isConfigured($provider),
                    'agents' => collect($agents->known())
                        ->filter(fn (string $agent): bool => $agents->providerName($agent) === $provider)
                        ->values()
                        ->all(),
                ])
                ->all(),
            'labs' => collect(Lab::cases())->map(fn (Lab $lab): string => $lab->value)->all(),
        ]);
    }

    public function update(ProviderKeyRequest $request, ProviderCredentials $credentials): RedirectResponse
    {
        $credentials->add(
            $request->string('provider')->value(),
            $request->string('key')->value(),
            $request->string('name')->value(),
        );

        return to_route('app-settings.provider.edit')->with('status', 'Key saved.');
    }

    public function destroy(string $provider, int $index, ProviderCredentials $credentials): RedirectResponse
    {
        $credentials->remove($provider, $index);

        // Not the same as having no key: the env may still supply one, and the
        // page says so on the next render rather than claiming the provider is
        // now unreachable.
        return to_route('app-settings.provider.edit')->with('status', 'Stored key removed.');
    }
}
