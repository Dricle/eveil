<?php

namespace App\Actions\Fortify;

use App\Actions\CreateAccount;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(protected CreateAccount $createAccount) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'organization' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = $this->createAccount->handle([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'organization' => $input['organization'],
        ]);

        // Carried from the marketing homepage's hero form, through the
        // register page, as a plain hidden field. Only a format check here:
        // this is never trusted to skip the real validation (reachability
        // included) that ProjectController::store runs when the pending
        // project is actually created, past the email-verification gate.
        // A malformed value must never fail the registration itself, so it
        // is dropped rather than validated through the throwing Validator
        // above.
        $url = $input['url'] ?? null;

        if (is_string($url) && str_starts_with($url, 'http') && filter_var($url, FILTER_VALIDATE_URL)) {
            Session::put('pending_project_url', $url);
        }

        return $user;
    }
}
