<?php

namespace Database\Seeders;

use App\Enums\HostKind;
use App\Models\KnownHost;
use Illuminate\Database\Seeder;

/**
 * The head start every install gets.
 *
 * A cloud instance grows this table from every customer's runs, so it is smart
 * within days. A fresh self-hosted install has nobody else's learnings and
 * would otherwise pay a model to work out that Yelp lists businesses. This seed
 * closes the cold start, and it ships identically in BOTH editions — the cloud
 * advantage is that its registry keeps growing, never that self-hosted was
 * given less.
 *
 * Adding a directory here is a one-row pull request, deliberately: nobody can
 * enumerate the world's trade directories, but a Belgian contributor knows the
 * Belgian ones and a German contributor knows the German ones.
 */
class KnownHostSeeder extends Seeder
{
    /**
     * Verdicts are STRUCTURAL and hold for every target profile — "does this
     * host list organisations?", never "would most customers care?". Job
     * boards, marketplaces and delivery platforms are indexes for everyone,
     * because a recruitment agency hunts companies that are hiring and a
     * food-tech product hunts restaurants. Whether the contents fit a given
     * profile is qualification's problem.
     *
     * @var array<string, array<int, string>>
     */
    private const HOSTS = [
        HostKind::Index->value => [
            // Generalist business directories.
            'pagesdor.be', 'pagesjaunes.fr', 'goudengids.be', 'infobel.com',
            'yelp.com', 'yellowpages.com', 'gelbeseiten.de', 'paginegialle.it',
            'thomsonlocal.com', 'kompass.com', 'europages.co.uk', 'cylex.net',
            // Reviews and hospitality, which double as business lists.
            'tripadvisor.com', 'thefork.com', 'resto.be', 'opentable.com',
            // Delivery platforms: a list of restaurants is a list of businesses.
            'deliveroo.com', 'ubereats.com', 'takeaway.com', 'justeat.com',
            // Software, startups and agencies.
            'producthunt.com', 'betalist.com', 'crunchbase.com', 'g2.com',
            'capterra.com', 'clutch.co', 'sortlist.com', 'trustpilot.com',
            // Job boards list companies that are hiring — the lead source for
            // anyone selling to employers, recruiters above all.
            'indeed.com', 'glassdoor.com', 'welcometothejungle.com', 'stepstone.com',
            // Code hosting lists organisations, which is where a developer-tool
            // profile finds its market.
            'github.com', 'gitlab.com',
            // Marketplaces list sellers.
            'amazon.com', 'ebay.com', 'etsy.com',
        ],
        HostKind::Social->value => [
            'facebook.com', 'instagram.com', 'linkedin.com', 'x.com', 'twitter.com',
            'tiktok.com', 'pinterest.com', 'youtube.com',
        ],
        HostKind::Other->value => [
            'wikipedia.org', 'reddit.com', 'medium.com', 'quora.com',
            'stackoverflow.com', 'substack.com', 'wordpress.com', 'blogspot.com',
        ],
    ];

    public function run(): void
    {
        foreach (self::HOSTS as $kind => $hosts) {
            foreach ($hosts as $host) {
                KnownHost::query()->updateOrCreate(
                    ['host' => $host],
                    [
                        'kind' => HostKind::from($kind),
                        'reason' => 'Shipped default.',
                        // Not locked: a seeded verdict is a good guess, not a
                        // human decision, and it should expire like any other.
                        'last_verified_at' => now(),
                    ],
                );
            }
        }
    }
}
