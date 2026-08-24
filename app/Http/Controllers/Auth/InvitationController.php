<?php

namespace App\Http\Controllers\Auth;

use App\Actions\AcceptInvitation;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * There is no `invitations` row: the signature on the URL itself is the
 * invite, so validity is `$request->hasValidSignature()` rather than a
 * lookup, and there is nothing to mark accepted or delete.
 *
 * Reachable whether the visitor is authenticated or not: an existing user
 * just accepts, a brand new one sets a name and password first. Neither
 * branch is `guest`-only, which is why this route sits outside that group.
 */
class InvitationController extends Controller
{
    public function __construct(private AcceptInvitation $accept) {}

    public function show(Request $request): Response
    {
        if (! $request->hasValidSignature()) {
            return Inertia::render('auth/InvitationInvalid');
        }

        $organization = Organization::query()->find($request->integer('organization'));

        if ($organization === null) {
            return Inertia::render('auth/InvitationInvalid');
        }

        return Inertia::render('auth/AcceptInvitation', [
            // The exact URL the signature was validated against, query string
            // (organization, email, role, expires, signature) included: the
            // POST reuses it verbatim rather than a Wayfinder route, which
            // has no way to know these dynamic, signed params.
            'acceptUrl' => $request->fullUrl(),
            'organizationName' => $organization->name,
            'email' => (string) $request->string('email'),
            'authenticatedAs' => Auth::user()?->email,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $organization = Organization::query()->findOrFail($request->integer('organization'));
        $role = OrganizationRole::from((string) $request->string('role'));
        $email = (string) $request->string('email');

        // Validation for the guest branch (name, password rules, the
        // email-not-already-taken check) all live in `AcceptInvitation`
        // itself: it is the one place that knows both shapes this call can
        // take, and a Form Request cannot express "no rules at all when
        // authenticated" as cleanly as the action already does.
        $newAccount = Auth::guest() ? $request->only('name', 'password', 'password_confirmation') : null;

        $user = $this->accept->handle($organization, $email, $role, Auth::user(), $newAccount);

        if (Auth::guest()) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect()->route('dashboard');
    }
}
