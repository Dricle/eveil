<?php

namespace App\Http\Controllers;

use App\Actions\UpdateInsight;
use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Http\Requests\InsightRequest;
use App\Http\Resources\InsightResource;
use App\Models\Insight;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Insights feed: everything Eveil noticed while reading, newest first.
 * Only open items are listed; one acted on or turned down leaves the feed.
 * `Insight` is project-scoped, so never route-model-bound
 * (`.ai/rules/controllers.md`).
 */
class InsightController extends Controller
{
    public function index(Request $request, CurrentProject $currentProject): Response
    {
        $kind = InsightKind::tryFrom($request->string('kind')->value());
        $competitor = $request->string('competitor')->value();
        $unread = $request->boolean('unread');

        $insights = Insight::query()
            ->where('status', InsightStatus::Open)
            ->inFeed()
            ->when($kind, fn ($query, InsightKind $kind) => $query->where('kind', $kind))
            ->when($competitor !== '' && $competitor !== 'all', fn ($query) => $query->where('details->competitor', $competitor))
            ->when($unread, fn ($query) => $query->whereNull('read_at'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $knowledgeBase = $currentProject->getOrFail()->knowledge_base ?? [];

        return Inertia::render('insights/Index', [
            'insights' => InsightResource::collection($insights),
            'filters' => ['kind' => $kind->value ?? 'all', 'competitor' => $competitor !== '' ? $competitor : 'all', 'unread' => $unread],
            'competitors' => array_values(array_filter(
                is_array($knowledgeBase['competitors'] ?? null) ? $knowledgeBase['competitors'] : [],
                fn (mixed $name): bool => is_string($name) && trim($name) !== '',
            )),
            'unreadCount' => Insight::query()->where('status', InsightStatus::Open)->inFeed()->whereNull('read_at')->count(),
            // One tab per kind, each with its size, so 26 missing features
            // never hide 3 acquisition ideas further down the feed.
            'counts' => Insight::query()->where('status', InsightStatus::Open)->inFeed()
                ->toBase()->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total', 'kind')
                ->map(fn (mixed $total): int => (int) $total),
        ]);
    }

    public function update(InsightRequest $request, UpdateInsight $updateInsight, int $insight): RedirectResponse
    {
        $updateInsight->handle(
            Insight::query()->findOrFail($insight),
            $request->has('read') ? $request->boolean('read') : null,
            InsightStatus::tryFrom($request->string('status')->value()),
        );

        return back();
    }
}
