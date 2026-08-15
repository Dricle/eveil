<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Two-factor enrolment is driven by Fortify's own routes — this only renders
 * the state they act on, so the QR code and the recovery codes never travel as
 * JSON the page has to assemble itself.
 */
class TwoFactorController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $enrolling = $user->two_factor_secret !== null;

        return Inertia::render('account/TwoFactor', [
            'twoFactorEnabled' => $enrolling,
            'twoFactorConfirmed' => $user->two_factor_confirmed_at !== null,
            'qrCode' => $enrolling ? $user->twoFactorQrCodeSvg() : null,
            'recoveryCodes' => $enrolling ? $user->recoveryCodes() : [],
        ]);
    }
}
