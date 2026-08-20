<?php

namespace Database\Seeders;

use App\Models\MailHost;
use Illuminate\Database\Seeder;

/**
 * The providers everyone already knows refuse probes wholesale, so a fresh
 * install does not spend a timeout rediscovering each one.
 *
 * Locked, like the certainties in `known_hosts`: observation never overwrites
 * them and a superadmin can still edit one. Everything else is learned by
 * talking to the server, which is free: the refusal IS the signal.
 */
class MailHostSeeder extends Seeder
{
    /** @var array<int, string> */
    private const REFUSERS = [
        'google.com', 'googlemail.com', 'outlook.com', 'protection.outlook.com',
        'hotmail.com', 'office365.com', 'yahoodns.net', 'icloud.com',
    ];

    public function run(): void
    {
        foreach (self::REFUSERS as $host) {
            MailHost::query()->updateOrCreate(['host' => $host], ['is_locked' => true]);
        }
    }
}
