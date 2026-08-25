<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a user together with the organization they own. Every user reaches
 * the app through here: setup on a fresh instance and public registration
 * alike, because a user without an organization can own nothing and would
 * fail on the first project they try to create.
 */
class CreateAccount
{
    public function __construct(private CreateOrganization $createOrganization) {}

    /**
     * @param  array{name: string, email: string, password: string, organization: string}  $data
     */
    public function handle(array $data, bool $isSuperAdmin = false): User
    {
        return DB::transaction(function () use ($data, $isSuperAdmin): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            if ($isSuperAdmin) {
                // The super admin is whoever ran the setup screen or set
                // ADMIN_EMAIL on their own box: nobody is confirming an
                // address for them. A public registration is the one path
                // where the address is a stranger's claim, and that is the
                // one case `MustVerifyEmail` exists to cover.
                $user->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();
            }

            $this->createOrganization->handle($data['organization'], $user);

            return $user;
        });
    }
}
