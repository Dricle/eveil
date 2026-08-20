<?php

namespace App\Console\Commands;

use App\Support\DisposableDomains;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pulls the current disposable-address blocklist.
 *
 * The bundled copy is a snapshot and goes stale: new throwaway services appear
 * weekly, and each one we do not know is an address we mark valid and send to.
 */
class RefreshDisposableCommand extends Command
{
    protected $signature = 'eveil:refresh-disposable {--url= : Override the upstream list}';

    protected $description = 'Refresh the disposable email domain blocklist';

    private const UPSTREAM = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf';

    public function handle(DisposableDomains $disposable): int
    {
        $url = (string) ($this->option('url') ?: self::UPSTREAM);

        try {
            $response = Http::timeout(60)->get($url);
        } catch (Throwable $e) {
            $this->components->error("Could not reach {$url}: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->components->error("{$url} answered HTTP {$response->status()}.");

            return self::FAILURE;
        }

        $domains = collect(explode("\n", $response->body()))
            ->map(fn (string $line): string => mb_strtolower(trim($line)))
            ->reject(fn (string $line): bool => $line === '' || str_starts_with($line, '#'));

        // A truncated response would quietly shrink the blocklist and start
        // letting throwaway addresses through, so refuse anything implausible
        // rather than replace a good list with a bad one.
        if ($domains->count() < 1_000) {
            $this->components->error("{$url} returned only {$domains->count()} domains. Refusing to replace the list.");

            return self::FAILURE;
        }

        $this->components->info(sprintf('Stored %s disposable domains.', number_format($disposable->replaceWith($domains))));

        return self::SUCCESS;
    }
}
