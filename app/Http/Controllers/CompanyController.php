<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\TargetProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the searches came back with. Every row here was found, fetched and read
 * — none of it was bought — and every score comes with the sentence that
 * justifies it, which is also the opening line of the email.
 *
 * Sorting and filtering are done by the database rather than in the browser:
 * the list is paginated, so a column sorted client-side would only sort the
 * twenty-five rows that happen to be on screen.
 */
class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $profile = $request->integer('profile') ?: null;
        $minScore = $request->integer('min_score');
        $rejected = $request->boolean('rejected');
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
            // Rejected is the user's own verdict, so it hides the row rather
            // than colouring it — unless they ask to see what they threw out.
            ->when(! $rejected, fn ($query) => $query->whereNull('rejected_at'))
            ->sorted($request->string('sort')->value(), $request->string('direction')->value())
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('leads/Companies', [
            'companies' => CompanyResource::collection($companies),
            'profiles' => TargetProfile::query()->orderBy('id')->get(['id', 'name']),
            'filters' => [
                'profile' => $profile,
                'min_score' => $minScore,
                'rejected' => $rejected,
                'search' => $search ?: null,
                'filter' => array_filter($columns, fn (?string $value): bool => $value !== null && $value !== ''),
                'sort' => $request->string('sort')->value() ?: null,
                'direction' => $request->string('direction')->value() ?: null,
            ],
            'total' => Company::query()->whereNull('rejected_at')->count(),
            // How many are worth a bulk search: nobody has looked at them yet.
            'unsearched' => Company::query()->whereNull('rejected_at')->whereNull('contacts_status')->count(),
        ]);
    }
}
