<?php

namespace App\Cloud;

use App\Ai\Contracts\SpendGuardInterface;
use App\Cloud\Ai\CreditSpendGuard;
use App\Cloud\Listeners\GrantCreditsOnCheckout;
use App\Cloud\Listeners\SavePaymentMethodOnSetup;
use App\Models\Organization;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Registered unconditionally in `bootstrap/providers.php` - loading the
 * class costs nothing, and `app/Cloud/` is deliberately a conditional-
 * loading mechanism, not a legal boundary, so the class existing in the
 * self-hosted codebase is the point, not an accident.
 *
 * Everything it DOES is gated on edition: self-hosted's `register()` is a
 * no-op, and `SpendGuardInterface` stays bound to `UnmeteredSpend`
 * (`AppServiceProvider`, registered before this one in the array, so this
 * provider's bind() is the one that wins when it does run).
 */
class CloudServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (config('eveil.edition') !== 'cloud') {
            return;
        }

        Cashier::useCustomerModel(Organization::class);

        $this->app->bind(SpendGuardInterface::class, CreditSpendGuard::class);

        // Explicit registration rather than relying on Laravel's listener
        // auto-discovery, which only scans `app/Listeners/` by default and
        // would silently miss these under `app/Cloud/Listeners/`.
        Event::listen(WebhookReceived::class, GrantCreditsOnCheckout::class);
        Event::listen(WebhookReceived::class, SavePaymentMethodOnSetup::class);
    }
}
