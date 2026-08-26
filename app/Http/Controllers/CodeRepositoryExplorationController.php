<?php

namespace App\Http\Controllers;

use App\Jobs\ExploreRepo;
use App\Models\CodeRepository;
use Illuminate\Http\RedirectResponse;

/**
 * The deep, tool-calling read of a repo — manual and priced, unlike the
 * lightweight one `CodeRepositoryController::store()` already starts for
 * free on link. Split out the same way `AutoTopUpController` sits beside
 * the billing page it saves into: one screen, two actions with two very
 * different costs.
 *
 * The id is looked up here rather than route-model-bound, same reasoning as
 * `CodeRepositoryController::destroy()`: `SubstituteBindings` resolves
 * before `project.set`, so a bound model would be fetched while the
 * project scope is still inert.
 */
class CodeRepositoryExplorationController extends Controller
{
    public function store(int $codeRepository): RedirectResponse
    {
        $repository = CodeRepository::query()->findOrFail($codeRepository);

        ExploreRepo::dispatch($repository);

        return to_route('settings.knowledge-base.edit');
    }
}
