<?php

namespace App\Cloud\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpfDmarcCheckRequest;
use App\Services\Outreach\SenderAuthentication;
use Illuminate\View\View;

class SpfDmarcCheckerController extends Controller
{
    public function __invoke(SpfDmarcCheckRequest $request, SenderAuthentication $checker): View
    {
        $domain = $request->string('domain')->toString();

        return view('marketing.tools.spf-dmarc', [
            'domain' => $domain,
            'result' => $checker->check($domain),
        ]);
    }
}
