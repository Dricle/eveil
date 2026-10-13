<?php

namespace App\Http\Controllers\Settings;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiTokenRequest;
use App\Http\Requests\ApiTokenRevokeRequest;
use App\Http\Resources\ApiTokenResource;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tokens for the public API and the MCP server. The organization owns each
 * one, and it reaches every project of the organization, including ones a
 * member cannot open: owners and admins alone create and revoke them
 * (`ApiTokenRequest`, `ApiTokenRevokeRequest`).
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
            'tokens' => ApiTokenResource::collection($this->currentProject->organization()->tokens()->latest('id')->get()),
            'plainTextToken' => $request->session()->get('plainTextToken'),
            'canManageTokens' => in_array($this->currentProject->organization()->roleOf($request->user()), [OrganizationRole::Owner, OrganizationRole::Admin], true),
            'apiUrl' => url('/api/v1'),
            'mcpUrl' => url('/mcp'),
        ]);
    }

    public function store(ApiTokenRequest $request): RedirectResponse
    {
        $token = $this->currentProject->organization()->createToken($request->validated('name'));

        return to_route('settings.api-tokens.index')
            ->with('plainTextToken', Str::after($token->plainTextToken, '|'));
    }

    public function destroy(ApiTokenRevokeRequest $request, int $token): RedirectResponse
    {
        $this->currentProject->organization()->tokens()->findOrFail($token)->delete();

        return to_route('settings.api-tokens.index');
    }
}
