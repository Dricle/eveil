<?php

namespace App\Actions;

use App\Enums\AdAccountStatus;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdMetric;
use App\Services\Ads\AdMetricRow;
use App\Services\Ads\AdPlatformException;
use Carbon\CarbonImmutable;

/**
 * Reads one account's numbers into `ad_metrics`, then checks whether it still
 * tracks conversions. The last week is always read again, since networks
 * revise recent days as late conversions land; after a gap, everything since
 * the last good read.
 *
 * Only ads Eveil created are kept: the account may run other campaigns that
 * are not ours to judge.
 */
class SyncAdMetrics
{
    public function handle(AdAccount $account): void
    {
        $client = $account->platform->client();
        $today = $account->today()->startOfDay();
        $from = CarbonImmutable::parse(($account->metrics_synced_at ?? now()->subDays(30))->toDateString())->min($today->subDays(7));

        try {
            $rows = $client->metrics($account, $from, $today);
            $tracksConversions = $client->hasConversionTracking($account);
        } catch (AdPlatformException $e) {
            $account->update([
                'status' => $e->revoked ? AdAccountStatus::Error : $account->status,
                'last_error' => $e->getMessage(),
            ]);

            return;
        }

        $ours = Ad::query()
            ->whereIn('ad_campaign_id', $account->campaigns()->select('id'))
            ->whereNotNull('external_id')
            ->pluck('id', 'external_id');

        $records = $rows
            ->filter(fn (AdMetricRow $row): bool => $ours->has($row->adExternalId))
            ->map(fn (AdMetricRow $row): array => [
                'ad_id' => $ours[$row->adExternalId],
                'date' => $row->date->toDateString(),
                'impressions' => $row->impressions,
                'clicks' => $row->clicks,
                'cost_micros' => $row->costMicros,
                'conversions' => $row->conversions,
                'conversion_value' => $row->conversionValue,
            ])
            ->values()
            ->all();

        AdMetric::query()->upsert($records, ['ad_id', 'date'], ['impressions', 'clicks', 'cost_micros', 'conversions', 'conversion_value']);

        $account->update([
            'has_conversion_tracking' => $tracksConversions,
            'metrics_synced_at' => now(),
            'last_error' => null,
        ]);
    }
}
