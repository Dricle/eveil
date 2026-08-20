<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyResource;
use App\Http\Resources\CompanySheetResource;
use App\Models\Company;
use App\Models\TargetProfile;
use App\Support\ProjectActivity;
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
        $search = $request->string('search')->trim()->value();

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
            // A company the user has taken out. A client, a closed deal, a
            // rejection: is not part of the list they are working through,
            // unless they ask to see what they set aside.
            ->when(! $excluded, fn ($query) => $query->contactable())
            ->sorted($request->string('sort')->value(), $request->string('direction')->value())
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('leads/Companies', [
            'companies' => CompanyResource::collection($companies),
            'profiles' => TargetProfile::query()->orderBy('id')->get(['id', 'name']),
            // So a list that is still filling up does not read as an empty one.
            'activity' => $activity->summary(),
            'filters' => [
                'profile' => $profile,
                'min_score' => $minScore,
                'excluded' => $excluded,
                'search' => $search ?: null,
                'filter' => array_filter($columns, fn (?string $value): bool => $value !== null && $value !== ''),
                'sort' => $request->string('sort')->value() ?: null,
                'direction' => $request->string('direction')->value() ?: null,
            ],
            'total' => Company::query()->contactable()->count(),
            // How many are worth a bulk search: nobody has looked at them yet.
            'unsearched' => Company::query()->contactable()->whereNull('contacts_status')->count(),
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
