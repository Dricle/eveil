<?php

namespace App\Http\Controllers\AppSettings;

use App\Enums\HostKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\KnownHostRequest;
use App\Http\Resources\KnownHostResource;
use App\Models\KnownHost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the instance has worked out about hosts on the public web, and the place
 * to correct it.
 *
 * A wrong verdict caches with exactly the same confidence as a right one, and
 * it is instance-wide: a real prospect filed as `other` becomes invisible to
 * every project at once. Correcting one locks it, and a locked verdict is never
 * rewritten by a model again.
 */
class KnownHostController extends Controller
{
    public function index(Request $request): Response
    {
        $kind = $request->string('kind')->value();
        $search = $request->string('search')->value();

        return Inertia::render('app-settings/Hosts', [
            'hosts' => KnownHostResource::collection(
                KnownHost::query()
                    ->when($kind !== '', fn ($query) => $query->where('kind', $kind))
                    ->when($search !== '', fn ($query) => $query->where('host', 'like', '%'.$search.'%'))
                    ->orderByDesc('businesses_found')
                    ->orderBy('host')
                    ->paginate(25)
                    ->withQueryString()
            ),
            'filters' => ['kind' => $kind, 'search' => $search],
            'kinds' => collect(HostKind::cases())->map(fn (HostKind $case): string => $case->value)->all(),
        ]);
    }

    public function update(KnownHostRequest $request, KnownHost $knownHost): RedirectResponse
    {
        $knownHost->update($request->validated());

        return back()->with('status', $knownHost->host.' updated.');
    }
}
