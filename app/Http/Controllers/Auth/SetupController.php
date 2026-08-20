<?php

namespace App\Http\Controllers\Auth;

use App\Actions\CreateAccount;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First run of a self-hosted instance: creates the super admin and the
 * organization they own. Self-hosted gets an organization like cloud does:
 * one code path, never two.
 */
class SetupController extends Controller
{
    public function __construct(protected CreateAccount $createAccount) {}

    public function create(): Response|RedirectResponse
    {
        if (User::query()->exists()) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/Setup');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(User::query()->exists(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'organization' => ['required', 'string', 'max:255'],
        ]);

        $user = $this->createAccount->handle($data, isSuperAdmin: true);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
