<?php

namespace App\Jobs;

use App\Enums\AdAccountStatus;
use App\Models\AdAccount;
use App\Models\AdConversion;
use App\Services\Ads\AdPlatformException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hands one reported goal to the network its click came from. The click id
 * does not say which account it belongs to, so every connected account of
 * that network is offered it until one takes it; the others refuse.
 *
 * A refusal is tried again later rather than given up on: a goal or action
 * the network just created may not take clicks for a few hours.
 */
class UploadAdConversion implements ShouldQueue
{
    use Queueable;

    /**
     * Seconds before each new try, spread over about a day.
     */
    private const RETRY_DELAYS = [600, 3600, 7200, 21600, 43200];

    /**
     * The first try plus one per delay: the worker's own limit is lower.
     */
    public int $tries = 6;

    public function __construct(public AdConversion $conversion) {}

    public function handle(): void
    {
        $conversion = $this->conversion;

        if ($conversion->uploaded_at !== null) {
            return;
        }

        $accounts = AdAccount::query()
            ->where('project_id', $conversion->project_id)
            ->where('platform', $conversion->platform)
            ->where('status', AdAccountStatus::Active)
            ->get();

        if ($accounts->isEmpty()) {
            $conversion->update(['last_error' => "No {$conversion->platform->label()} account is connected to report it to."]);

            return;
        }

        $refusals = [];

        foreach ($accounts as $account) {
            try {
                $account->platform->client()->uploadConversion($account, $conversion);
            } catch (AdPlatformException $e) {
                $refusals[] = $e->getMessage();

                continue;
            }

            $conversion->update(['uploaded_at' => now(), 'last_error' => null]);
            // The network now counts conversions here, tag or not: bid on them.
            $account->update(['has_conversion_tracking' => true]);

            return;
        }

        $conversion->update(['last_error' => implode(' ', array_unique($refusals))]);

        // Released rather than thrown: a refusal is the network's answer, not
        // a bug to report. Past the last delay the error stays on the row.
        $delay = self::RETRY_DELAYS[$this->attempts() - 1] ?? null;

        if ($delay !== null) {
            $this->release($delay);
        }
    }
}
