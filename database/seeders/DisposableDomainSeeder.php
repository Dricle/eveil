<?php

namespace Database\Seeders;

use App\Support\DisposableDomains;
use Illuminate\Database\Seeder;

/**
 * Loads the bundled blocklist. Ships in both editions and needs no network:
 * a self-hosted install behind a firewall must still reject throwaway
 * addresses, because sending to one is a bounce and bounces cost the domain.
 */
class DisposableDomainSeeder extends Seeder
{
    public function run(): void
    {
        app(DisposableDomains::class)->replaceWith(DisposableDomains::bundled());
    }
}
