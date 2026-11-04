<?php

namespace App\Cloud\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmailCheckRequest;
use App\Services\Discovery\EmailCheck;
use Illuminate\View\View;

class EmailCheckerController extends Controller
{
    public function __invoke(EmailCheckRequest $request, EmailCheck $checker): View
    {
        $email = $request->string('email')->toString();

        return view('marketing.tools.email-checker', [
            'email' => $email,
            'result' => $checker->check($email),
        ]);
    }
}
