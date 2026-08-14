<?php

namespace App\Actions;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a user together with the organization they own. Every user reaches
 * the app through here — setup on a fresh instance and public registration
 * alike — because a user without an organization can own nothing and would
 * fail on the first project they try to create.
 */
class CreateAccount
{
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
                $user->forceFill(['is_super_admin' => true])->save();
            }

            $organization = Organization::create(['name' => $data['organization']]);

            $organization->users()->attach($user, ['role' => OrganizationRole::Owner->value]);

            return $user;
        });
    }
}
