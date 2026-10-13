<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use App\Support\TokenScope;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns the bearer token into a `TokenScope`, for the API and the MCP server.
 *
 * An API token's owner is an organization. An OAuth token (MCP only) belongs
 * to a user and reaches what they can open, checked again on every call by
 * the scope itself: losing access to a project, or leaving an organization,
 * takes it away from their assistants too, with no token to revoke by hand.
 *
 * No project is set here: which one a call acts on is chosen per call, by
 * `SetTokenProject` for the API and `CallProjectTool` for MCP.
 */
class SetTokenScope
{
    public function handle(Request $request, Closure $next): Response
    {
        // Each guard asked by name, in `auth:sanctum,api`'s order: the default
        // user is typed as a `User`, and an API token's owner is not one.
        $owner = Auth::guard('sanctum')->user() ?? Auth::guard('api')->user();

        $scope = match (true) {
            $owner instanceof Organization => TokenScope::forOrganization($owner),
            $owner instanceof User && $this->isMcpToken($owner) => TokenScope::forUser($owner),
            default => null,
        };

        // 403, not 401, once the token itself is good: a 401 sends an MCP
        // client back through sign-in, which Passport approves on its own
        // while the client holds a live token, and round it goes.
        abort_if($owner === null, 401);
        abort_unless($scope instanceof TokenScope, 403);

        app()->instance(TokenScope::class, $scope);

        return $next($request);
    }

    /**
     * A real access token carrying the MCP scope. Passport's cookie-based
     * transient token, the one the app's own pages could carry, is refused.
     */
    private function isMcpToken(User $user): bool
    {
        $token = $user->token();

        return $token instanceof AccessToken && $token->can(Registrar::OAUTH_SCOPE);
    }
}
