<?php

namespace App\Actions;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a user has to decide what happens to the organizations they belong
 * to. An organization nobody is left in is unreachable: nothing can grant
 * access to it again, so it goes, taking its projects and everything hanging
 * off them through the foreign keys. Organizations with other members survive:
 * one person leaving a team is not the team being deleted.
 */
class DeleteAccount
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $organizationIds = $user->organizations()->pluck('organizations.id');

            $user->delete();

            Organization::query()
                ->whereIn('id', $organizationIds)
                ->whereDoesntHave('users')
                ->each(fn (Organization $organization) => $organization->delete());
        });
    }
}
