<?php

namespace App\Http\Controllers\AppSettings;

use App\Ai\AgentSettings;
use App\Cloud\Models\CreditPrice;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\CreditPriceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * Cloud's calibration screen for ADR-019's per-agent grid. Reachable on
 * self-hosted too (`.ai/rules/controllers.md`'s "404 for access, not a
 * feature that doesn't apply here") — the row it writes just sits unused,
 * since `UnmeteredSpend` never queries `credit_prices` there.
 */
class CreditPriceController extends Controller
{
    public function store(CreditPriceRequest $request, string $agent, AgentSettings $agents): RedirectResponse
    {
        abort_unless(in_array($agent, $agents->known(), true), 404);

        // A new row, never an update: a transaction already charged at the
        // old rate must stay reproducible.
        CreditPrice::create([
            'agent' => $agent,
            'credits' => $request->integer('credits'),
            'effective_from' => Carbon::now(),
        ]);

        return to_route('app-settings.agents.index')->with('status', $agent.' now costs '.$request->integer('credits').' credits.');
    }
}
