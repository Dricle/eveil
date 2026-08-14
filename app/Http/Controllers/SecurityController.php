<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account security. Two-factor enrolment is driven by Fortify's own routes —
 * this only renders the state they act on, so the QR code and the recovery
 * codes never travel as JSON the page has to assemble itself.
 */
class SecurityController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $enrolling = $user->two_factor_secret !== null;

        return Inertia::render('Security', [
            'twoFactorEnabled' => $enrolling,
            'twoFactorConfirmed' => $user->two_factor_confirmed_at !== null,
            'qrCode' => $enrolling ? $user->twoFactorQrCodeSvg() : null,
            'recoveryCodes' => $enrolling ? $user->recoveryCodes() : [],
        ]);
    }
}
