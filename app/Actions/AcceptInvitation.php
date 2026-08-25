<?php

namespace App\Actions;

use App\Actions\Fortify\PasswordValidationRules;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Joins the organization named in a valid signed invite link. Deliberately
 * NOT `CreateAccount`: that action exists to guarantee a user never lacks an
 * organization, and an invitee never lacks one either, they are joining one
 * that already exists rather than starting their own.
 */
class AcceptInvitation
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>|null  $newAccount  name/password, required when $authenticated is null
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, string $email, OrganizationRole $role, ?User $authenticated, ?array $newAccount = null): User
    {
        return DB::transaction(function () use ($organization, $email, $role, $authenticated, $newAccount): User {
            $user = $authenticated ?? $this->createUser($email, $newAccount ?? []);

            $organization->users()->syncWithoutDetaching([
                $user->id => ['role' => $role->value],
            ]);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createUser(string $email, array $data): User
    {
        Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ])->validate();

        // The email is the invite link's, never taken from input: an
        // unauthenticated accept could otherwise create an account under any
        // address the link's owner chooses to type.
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['name' => ["An account already exists for {$email}. Log in, then open the invitation link again."]]);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $email,
            'password' => $data['password'],
        ]);

        // Clicking a signed link mailed to this exact address already proves
        // ownership of it: asking them to then also click a SECOND link, from
        // a second email, to verify the same address would be pure friction.
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
