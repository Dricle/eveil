<?php

namespace App\Actions;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            $organization = Organization::create([
                'name' => $data['organization'],
                'slug' => $this->uniqueSlug($data['organization']),
            ]);

            $organization->users()->attach($user, ['role' => OrganizationRole::Owner->value]);

            return $user;
        });
    }

    /**
     * Two companies picking the same name is ordinary, and the slug is unique.
     */
    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        $suffix = 1;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }
}
