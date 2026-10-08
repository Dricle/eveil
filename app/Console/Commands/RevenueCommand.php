<?php

namespace App\Console\Commands;

use App\Cloud\Models\CreditTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * "How much did Eveil make last month", answered without opening Stripe.
 *
 * Arbitrary-amount top-ups mean there is no Stripe product or price to group
 * by, so the dashboard has nothing to report against and MRR tooling reads
 * zero however much was collected. The ledger does have it, one row per
 * purchase, and that is what this reads.
 *
 * A command rather than a screen or an endpoint, deliberately: this is a
 * founder's number, read occasionally, and shell access is already exactly the
 * right authorisation for it. An endpoint would mean a new authenticated,
 * money-shaped surface to get wrong; a screen would mean a route, a controller,
 * a page and a permission scope for something nobody looks at daily. Both are
 * worth building the day the number stops being zero.
 */
class RevenueCommand extends Command
{
    protected $signature = 'eveil:revenue
        {--month= : Calendar month to report, as YYYY-MM. Defaults to the current one.}
        {--all : Every purchase ever recorded, ignoring --month.}';

    protected $description = 'Report credit top-up revenue actually collected, per currency';

    public function handle(): int
    {
        if (config('eveil.edition') !== 'cloud') {
            $this->warn('Nothing to report: this is a self-hosted instance, which never bills anybody.');

            return self::SUCCESS;
        }

        $query = CreditTransaction::query()->where('type', 'grant_purchase');

        if ($this->option('all')) {
            $label = 'all time';
        } else {
            $month = $this->month();

            if ($month === null) {
                $this->error('--month must look like 2026-09.');

                return self::FAILURE;
            }

            $label = $month->format('F Y');
            $query->whereBetween('created_at', [$month->startOfMonth(), $month->copy()->endOfMonth()]);
        }

        $purchases = $query->with('organization:id,name')->orderBy('created_at')->get();

        $this->line('');
        $this->info("Eveil credit revenue - {$label}");
        $this->line('');

        if ($purchases->isEmpty()) {
            $this->line('  No credit purchases. Revenue: 0.');
            $this->line('');

            return self::SUCCESS;
        }

        $this->totals($purchases);
        $this->buyers($purchases);

        return self::SUCCESS;
    }

    /**
     * Grouped by currency and never summed across them: the organization's
     * currency is chosen per customer, so one total would be adding euros to
     * dollars at an exchange rate nobody recorded.
     *
     * @param  Collection<int, CreditTransaction>  $purchases
     */
    private function totals(Collection $purchases): void
    {
        $rows = $purchases
            ->whereNotNull('amount_cents')
            ->groupBy(fn (CreditTransaction $purchase): string => $purchase->currency ?? 'unknown')
            ->map(fn (Collection $group, string $currency): array => [
                strtoupper($currency),
                number_format($group->sum('amount_cents') / 100, 2),
                $group->count(),
                $group->unique('organization_id')->count(),
            ])
            ->values()
            ->all();

        if ($rows !== []) {
            $this->table(['Currency', 'Collected', 'Purchases', 'Organizations'], $rows);
        }

        // Purchases written before the amount was recorded at all. Reported
        // rather than ignored: a silently understated total is worse than a
        // stated gap, and multiplying credits back through today's rate to
        // guess would invent a number that looks authoritative.
        $unpriced = $purchases->whereNull('amount_cents');

        if ($unpriced->isNotEmpty()) {
            $this->warn(sprintf(
                '  %d purchase(s) carry no recorded amount and are excluded above. They predate the amount column; read them from Stripe.',
                $unpriced->count(),
            ));
        }
    }

    /**
     * @param  Collection<int, CreditTransaction>  $purchases
     */
    private function buyers(Collection $purchases): void
    {
        $this->line('');
        $this->line('  Who paid:');

        $rows = $purchases
            ->groupBy('organization_id')
            ->map(fn (Collection $group): array => [
                $group->first()?->organization?->name ?? 'deleted organization',
                $group->whereNotNull('amount_cents')->isEmpty()
                    ? 'unknown'
                    : number_format($group->sum('amount_cents') / 100, 2).' '.strtoupper($group->first()?->currency ?? ''),
                $group->sum('credits'),
                $group->min('created_at')?->toDateString() ?? '',
            ])
            ->values()
            ->all();

        $this->table(['Organization', 'Paid', 'Credits', 'First purchase'], $rows);
    }

    /**
     * Shape-checked before parsing rather than relying on the parser to
     * object: `Carbon::createFromFormat` is lenient enough to accept input
     * nobody meant, and a report silently covering the wrong month is the one
     * failure mode here that looks like an answer.
     */
    private function month(): ?Carbon
    {
        $option = $this->option('month');

        if (! is_string($option) || $option === '') {
            return now()->startOfMonth();
        }

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $option) !== 1) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $option.'-01')->startOfMonth();
    }
}
