<?php

namespace App\Support;

use App\Enums\SuppressionLayer;
use App\Models\Suppression;
use Illuminate\Support\Facades\DB;

/**
 * Domains that hand out throwaway addresses.
 *
 * This was a const of twelve inside `EmailVerifier`. There are north of eight
 * thousand and new ones daily, so twelve was not a short list, it was a wrong
 * answer: a throwaway domain has working MX and passes every other check we
 * make, so a miss means we mark the address valid and send to it.
 *
 * Not learnable either: you would have to send to a throwaway to find out,
 * and not guessable. It is a maintained public dataset, so it is treated as
 * one: bundled at `database/data/disposable-email-domains.txt` so a fresh
 * install needs no network, refreshed by `eveil:refresh-disposable`.
 *
 * Stored in `suppressions` on the `toxic` layer rather than a table of its own.
 * That layer already means "instance-wide, fed only by public lists and our own
 * detection, never by a client's prospect behaviour": which is exactly this.
 */
class DisposableDomains
{
    public const SOURCE = 'disposable-email-domains';

    /** @var array<string, bool> */
    private array $memo = [];

    public function includes(string $domain): bool
    {
        $domain = mb_strtolower(trim($domain));

        return $this->memo[$domain] ??= Suppression::query()
            ->where('layer', SuppressionLayer::Toxic)
            ->where('domain', $domain)
            ->exists();
    }

    /**
     * Replaces the whole set in one transaction, so a refresh that dies halfway
     * cannot leave the instance with a partial blocklist. Which would silently
     * start accepting throwaway addresses it used to reject.
     *
     * @param  iterable<int, string>  $domains
     * @return int how many are now stored
     */
    public function replaceWith(iterable $domains, string $source = self::SOURCE): int
    {
        $rows = collect($domains)
            ->map(fn (string $domain): string => mb_strtolower(trim($domain)))
            ->filter()
            ->unique()
            ->map(fn (string $domain): array => [
                'layer' => SuppressionLayer::Toxic->value,
                'domain' => $domain,
                'reason' => 'Disposable address provider.',
                'source' => $source,
                'created_at' => now(),
            ]);

        DB::transaction(function () use ($rows, $source): void {
            Suppression::query()
                ->where('layer', SuppressionLayer::Toxic)
                ->where('source', $source)
                ->delete();

            $rows->chunk(1_000)->each(
                fn ($chunk) => Suppression::query()->insert($chunk->values()->all())
            );
        });

        $this->memo = [];

        return $rows->count();
    }

    /**
     * @return array<int, string>
     */
    public static function bundled(): array
    {
        $path = database_path('data/disposable-email-domains.txt');

        if (! is_file($path)) {
            return [];
        }

        return array_values(array_filter(array_map(
            trim(...),
            explode("\n", (string) file_get_contents($path)),
        )));
    }
}
