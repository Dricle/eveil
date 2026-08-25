<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * What every real install needs, in both editions — never `DatabaseSeeder`
 * itself, which also creates a `test@example.com` user for local dev/CI.
 * Run from `composer setup` and the deploy entrypoint, on every boot: all
 * three seeders below are `updateOrCreate`/transactional-replace, so running
 * this again is a no-op, not a duplicate.
 */
class InstallSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([KnownHostSeeder::class, DisposableDomainSeeder::class, MailHostSeeder::class]);
    }
}
