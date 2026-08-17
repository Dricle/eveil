<?php

namespace App\Http\Middleware;

use App\Ai\Agents\TargetProfileDeriver;
use App\Enums\AgentRunStatus;
use App\Http\Resources\TargetProfileResource;
use App\Models\AgentRun;
use App\Models\TargetProfile;
use App\Support\CurrentProject;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The profiles are the navigation of the Targets section, so every page under
 * it needs the same list and the same "a derivation is running" flag — the
 * screen you are on has no bearing on either.
 *
 * Shared as closures: they only cost a query on the requests that actually
 * render a page.
 */
class ShareTargetProfiles
{
    private ?AgentRun $lastDerivation = null;

    public function __construct(private CurrentProject $currentProject) {}

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share([
            'profiles' => fn () => TargetProfileResource::collection(
                TargetProfile::query()->orderBy('id')->get()
            ),
            'deriving' => fn (): bool => $this->lastDerivation()?->isInFlight() ?? false,
            'derivationError' => function (): ?string {
                $run = $this->lastDerivation();

                return $run?->status === AgentRunStatus::Failed ? $run->error : null;
            },
            'analyzed' => fn (): bool => $this->currentProject->getOrFail()->knowledge_base !== null,
        ]);

        return $next($request);
    }

    /**
     * Memoised: the shared props ask about it twice, and it is one query.
     */
    private function lastDerivation(): ?AgentRun
    {
        return $this->lastDerivation ??= AgentRun::query()
            ->latestFor(TargetProfileDeriver::slug())
            ->first();
    }
}
