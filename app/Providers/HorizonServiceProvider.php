<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * The dashboard exposes every job payload on the instance: lead names,
     * addresses, message bodies. It is instance scope, so it goes to the
     * person who runs the instance and to nobody granted access through an
     * organization.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user): bool => $user?->is_super_admin === true);
    }
}
