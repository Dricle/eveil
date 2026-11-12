<?php

namespace App\Cloud\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliverabilityTestRequest;
use App\Services\Outreach\DeliverabilityReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A test address a visitor mails from their own mailbox, and the report on
 * what arrived. Nothing touches the database: the address and its report
 * live in the cache, and the message itself is graded then dropped.
 */
class DeliverabilityTesterController extends Controller
{
    /**
     * How long an address accepts its one mail.
     */
    public const ADDRESS_TTL_SECONDS = 3600;

    /**
     * How long the report stays readable, so the link survives a reload or
     * being sent to a colleague.
     */
    public const REPORT_TTL_SECONDS = 86400;

    public const MAX_MESSAGE_BYTES = 2 * 1024 * 1024;

    public function store(DeliverabilityTestRequest $request): RedirectResponse
    {
        $token = Str::lower(Str::random(16));

        Cache::put(self::key($token), ['report' => null], self::ADDRESS_TTL_SECONDS);

        return redirect()->route('tools.deliverability.show', $token);
    }

    public function show(string $token): Response
    {
        abort_unless(config('eveil.edition') === 'cloud', 404);

        $entry = Cache::get(self::key($token));

        abort_if($entry === null, 404);

        $response = response()->view('marketing.tools.deliverability-tester', [
            'address' => 't-'.$token.'@'.config('services.mail_tester.domain'),
            'report' => $entry['report'],
        ]);

        // Reloads itself until the mail lands: no script to keep in step
        // with the server.
        return $entry['report'] === null ? $response->header('Refresh', '5') : $response;
    }

    /**
     * Posted by the Email Routing worker, once per mail received on the test
     * domain. Anything not addressed to a live, unused test address gets a
     * 404, which the worker turns into an SMTP rejection: no mailbox fills
     * up with what spammers send to the domain.
     */
    public function inbound(Request $request, DeliverabilityReport $report): Response
    {
        $secret = (string) config('services.mail_tester.secret');

        abort_unless(config('eveil.edition') === 'cloud' && $secret !== '', 404);

        $raw = $request->getContent();
        $to = strtolower((string) $request->header('X-Envelope-To'));
        $expected = hash_hmac('sha256', $to."\n".$raw, $secret);

        abort_unless(hash_equals($expected, (string) $request->header('X-Signature')), 403);
        abort_if(strlen($raw) > self::MAX_MESSAGE_BYTES, 413);

        $domain = preg_quote(strtolower((string) config('services.mail_tester.domain')), '/');

        abort_unless(preg_match('/^t-([a-z0-9]{16})@'.$domain.'$/', $to, $match) === 1, 404);

        $key = self::key($match[1]);

        // One mail per address. `add` is atomic, so two mails landing at
        // once cannot both be graded.
        abort_unless(Cache::has($key) && Cache::add($key.':received', true, self::ADDRESS_TTL_SECONDS), 404);

        Cache::put($key, ['report' => $report->grade($raw)], self::REPORT_TTL_SECONDS);

        return response()->noContent();
    }

    private static function key(string $token): string
    {
        return 'mail-tester:'.$token;
    }
}
