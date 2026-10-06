<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiTokenRequest;
use App\Http\Resources\ApiTokenResource;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tokens for the public API, one project each: the project owns the token,
 * so what it can reach is exactly what this screen's project can.
 *
 * The secret is shown once, flashed straight after creation: only its hash is
 * stored. Sanctum hands it back as `<id>|eveil_...`, and the part before the
 * bar is cut off here: Sanctum finds a token by its hash alone when there is
 * no id, and `eveil_...` on its own is what secret scanners recognise.
 */
class ApiTokenController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function index(Request $request): Response
    {
        return Inertia::render('settings/ApiTokens', [
            'tokens' => ApiTokenResource::collection($this->currentProject->getOrFail()->tokens()->latest('id')->get()),
            'plainTextToken' => $request->session()->get('plainTextToken'),
            'apiUrl' => url('/api/v1'),
        ]);
    }

    public function store(ApiTokenRequest $request): RedirectResponse
    {
        $token = $this->currentProject->getOrFail()->createToken($request->validated('name'));

        return to_route('settings.api-tokens.index')
            ->with('plainTextToken', Str::after($token->plainTextToken, '|'));
    }

    public function destroy(int $token): RedirectResponse
    {
        $this->currentProject->getOrFail()->tokens()->findOrFail($token)->delete();

        return to_route('settings.api-tokens.index');
    }
}
