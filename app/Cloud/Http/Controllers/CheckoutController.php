<?php

namespace App\Cloud\Http\Controllers;

use App\Cloud\Actions\StartCheckout;
use App\Http\Controllers\Controller;
use App\Http\Requests\TopUpRequest;
use App\Support\CurrentProject;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function store(TopUpRequest $request, StartCheckout $startCheckout): Response
    {
        $checkout = $startCheckout->handle(
            $this->currentProject->organization(),
            $request->integer('amount_cents'),
        );

        // The Inertia `<Form>` this posts from sends an XHR expecting an
        // Inertia response back; a plain redirect to Stripe's own hosted
        // page is not one, and the client tries to render Stripe's HTML as
        // a page and fails. `Inertia::location()` answers with a 409 +
        // `X-Inertia-Location` instead, which tells the client to leave the
        // SPA and do a real `window.location` visit — same fix as logout.
        return Inertia::location($checkout->redirect());
    }
}
