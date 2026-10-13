<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConnectedAssistantResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Client;

/**
 * The AI assistants this user connected to the MCP server by signing in:
 * every OAuth client still holding a live token of theirs. An account
 * matter, not an organization's: the assistant acts as the person, in every
 * project they can open.
 *
 * Disconnecting revokes the tokens, refresh tokens included. Passport skips
 * the consent screen for a client that still holds a live one, so leaving
 * the refresh token would let the assistant back in without asking.
 */
class ConnectedAssistantController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('account/Assistants', [
            'assistants' => ConnectedAssistantResource::collection(
                Client::query()
                    ->whereHas('tokens', fn ($tokens) => $tokens->where('user_id', $request->user()->id)->where('revoked', false)->where('expires_at', '>', now()))
                    ->withMax(['tokens' => fn ($tokens) => $tokens->where('user_id', $request->user()->id)], 'created_at')
                    ->orderBy('name')
                    ->get()
            ),
        ]);
    }

    public function destroy(Request $request, string $client): RedirectResponse
    {
        $request->user()->tokens()->where('client_id', $client)->with('refreshToken')->get()->each(function ($token): void {
            $token->revoke();
            $token->refreshToken?->revoke();
        });

        return to_route('account.assistants.index');
    }
}
