<?php

namespace App\Http\Controllers;

use App\Enums\OutreachStatus;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\CompanySheetResource;
use App\Models\Company;
use App\Models\TargetProfile;
use App\Support\ProjectActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the searches came back with. Every row here was found, fetched and read
 *: none of it was bought, and every score comes with the sentence that
 * justifies it, which is also the opening line of the email.
 *
 * Sorting and filtering are done by the database rather than in the browser:
 * the list is paginated, so a column sorted client-side would only sort the
 * twenty-five rows that happen to be on screen.
 */
class CompanyController extends Controller
{
    public function index(Request $request, ProjectActivity $activity): Response
    {
        $profile = $request->integer('profile') ?: null;
        $minScore = $request->integer('min_score');
        $excluded = $request->boolean('excluded');
        $unapproved = $request->boolean('unapproved');
        $search = $request->string('search')->trim()->value();
        // The status pills above the list: each one is a whole replacement for
        // "which companies", not another switch stacked on `excluded`/
        // `unapproved` above - so only one of these is ever active. `null`
        // is "All", which stays exactly today's default (contactable, every
        // status left in outreach).
        $view = $request->string('view')->value() ?: null;

        /** @var array<string, string|null> $columns */
        $columns = $request->collect('filter')
            ->only(Company::FILTERS)
            ->map(fn ($value): ?string => is_string($value) ? trim($value) : null)
            ->all();

        $companies = Company::query()
            ->withBestFit()
            ->withCount(['leads as contacts_count' => fn ($query) => $query->whereNull('erased_at')])
            ->with(['evaluations' => fn ($query) => $query->with('targetProfile')->orderByDesc('fit_score')])
            ->matching($search)
            ->whereColumns($columns)
            ->when($profile, fn ($query) => $query->whereHas('evaluations', fn ($e) => $e->where('target_profile_id', $profile)))
            ->when($minScore, fn ($query) => $query->whereHas('evaluations', fn ($e) => $e->where('fit_score', '>=', $minScore)))
            // "Set aside" is the one pill that reaches outside outreach
            // entirely, so it is the one case `contactable()` is skipped
            // rather than narrowed further.
            ->when($view === 'set_aside', fn ($query) => $query->whereIn('status', OutreachStatus::excluded()))
            // A company the user has taken out. A client, a closed deal, a
            // rejection: is not part of the list they are working through,
            // unless they ask to see what they set aside.
            ->when($view !== 'set_aside' && ! $excluded, fn ($query) => $query->contactable())
            // The working queue: what is still waiting for a yes. Under
            // anything but full autonomy this is the only list that matters,
            // because nothing else moves until these are decided.
            ->when($unapproved || $view === 'awaiting', fn ($query) => $query->whereNull('approved_at'))
            ->when($view === 'approved', fn ($query) => $query->approved())
            ->when($view === 'no_contact', fn ($query) => $query->whereDoesntHave(
                'leads', fn (Builder $leads) => $leads->whereNull('erased_at')
            ))
            ->sorted($request->string('sort')->value(), $request->string('direction')->value())
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('leads/Companies', [
            'companies' => CompanyResource::collection($companies),
            'profiles' => TargetProfile::query()->orderBy('id')->get(['id', 'name']),
            // So a list that is still filling up does not read as an empty one.
            'activity' => $activity->summary(),
            // Flashed by KnownClientController, so it appears once on the list
            // it just changed.
            'knownClients' => $request->session()->get('known_clients'),
            'filters' => [
                'profile' => $profile,
                'min_score' => $minScore,
                'excluded' => $excluded,
                'unapproved' => $unapproved,
                'view' => $view,
                'search' => $search ?: null,
                'filter' => array_filter($columns, fn (?string $value): bool => $value !== null && $value !== ''),
                'sort' => $request->string('sort')->value() ?: null,
                'direction' => $request->string('direction')->value() ?: null,
            ],
            'total' => Company::query()->contactable()->count(),
            // How many are worth a bulk search: nobody has looked at them yet.
            'unsearched' => Company::query()->contactable()->whereNull('contacts_status')->count(),
            // How many are still waiting for a yes, which is the only number
            // that says whether the user has work to do here.
            'unapproved' => Company::query()->contactable()->whereNull('approved_at')->count(),
            // The count behind each status pill. Unfiltered by search/profile
            // like `total`/`unsearched`/`unapproved` above, on purpose: a pill
            // says how big each bucket is, not how many match what is
            // currently typed into the search box.
            'counts' => [
                'all' => Company::query()->contactable()->count(),
                'awaiting' => Company::query()->contactable()->whereNull('approved_at')->count(),
                'approved' => Company::query()->contactable()->approved()->count(),
                'no_contact' => Company::query()->contactable()->whereDoesntHave(
                    'leads', fn (Builder $leads) => $leads->whereNull('erased_at')
                )->count(),
                'set_aside' => Company::query()->whereIn('status', OutreachStatus::excluded())->count(),
            ],
        ]);
    }

    /**
     * Everything found about one company, and the people found at it.
     *
     * A drill-down from the list rather than a section of its own: this is where
     * you land after reading a fit score and wanting to know what it was based
     * on, and where the contacts appear as they are extracted.
     */
    public function show(ProjectActivity $activity, int $company): Response
    {
        $company = Company::query()
            ->with(['evaluations.targetProfile', 'leads' => fn ($leads) => $leads->orderBy('id')])
            ->withBestFit()
            ->findOrFail($company);

        return Inertia::render('leads/Company', [
            'company' => CompanySheetResource::make($company),
            'activity' => $activity->summary(),
        ]);
    }
}
