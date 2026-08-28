<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify's own default is a plain `redirect('/')`. Inertia's client follows
 * that XHR redirect and tries to render whatever `/` returns AS an Inertia
 * page - the marketing homepage isn't one, so it lands mangled inside the
 * app's SPA shell instead of a real navigation. `Inertia::location()` sends
 * a 409 with `X-Inertia-Location`, which the client recognises as "leave the
 * SPA" and does a full `window.location` visit instead.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return Inertia::location('/');
    }
}
