<?php

namespace App\Http\Controllers;

use App\Actions\DisconnectIntegration;
use App\Actions\DropInstagramMetaConnection;
use App\Enums\SocialPlatform;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Drops ONE of an Instagram account's two ways in, keeping the account on
 * the other: its own Meta app (`meta`) or its Composio connection
 * (`composio`). An account left with neither is removed. Disconnecting the
 * whole account is `SocialAccountController::destroy()`.
 */
class InstagramConnectionController extends Controller
{
    public function destroy(Request $request, DropInstagramMetaConnection $dropMeta, DisconnectIntegration $disconnect, int $socialAccount, string $connection): RedirectResponse
    {
        $account = SocialAccount::query()->ownedBy($request->user())
            ->where('platform', SocialPlatform::Instagram)
            ->findOrFail($socialAccount);

        if ($connection === 'meta' && $account->platform_app_id !== null) {
            $dropMeta->handle($account);
        }

        if ($connection === 'composio' && $account->integration !== null) {
            $disconnect->handle($account->integration);
        }

        return back();
    }
}
