<?php

namespace App\Services\Outreach;

use App\Models\Company;
use DateTimeZone;
use Locale;

/**
 * Where the recipient's working day is, from what discovery already wrote
 * down: the company's `location`, then its country-code domain.
 *
 * A campaign reaching Brussels and Montreal from one mailbox should land in
 * office hours in both, and the instance clock only knows one of them. Only a
 * country with a single zone answers outright; a country with several needs a
 * city that names one of its zones ("Chicago", "Toronto"); anything vaguer
 * falls back on the country's main zone, where most of its offices are.
 * Null only when the country itself is unknown, which leaves the mail on the
 * instance's own window.
 */
class RecipientTimezone
{
    /** @var array<string, string>|null country name in any of LANGUAGES => ISO code */
    private static ?array $countries = null;

    /**
     * Locations come in the language of the site they were read from:
     * "Belgique", "Deutschland", "Nederland".
     */
    private const LANGUAGES = ['en', 'fr', 'de', 'nl', 'es', 'it', 'pt'];

    /**
     * Where a country spread over more than an hour does business when
     * nothing names the city: a few hours off at worst for the far side of
     * it, where the instance clock was up to half a day off.
     */
    private const MAIN_ZONES = [
        'AU' => 'Australia/Sydney',
        'BR' => 'America/Sao_Paulo',
        'CA' => 'America/Toronto',
        'CL' => 'America/Santiago',
        'CN' => 'Asia/Shanghai',
        'GL' => 'America/Nuuk',
        'ID' => 'Asia/Jakarta',
        'KI' => 'Pacific/Tarawa',
        'MX' => 'America/Mexico_City',
        'RU' => 'Europe/Moscow',
        'US' => 'America/New_York',
    ];

    public function for(?Company $company): ?string
    {
        if ($company === null) {
            return null;
        }

        $location = mb_strtolower((string) $company->location);
        $country = $this->countryIn($location) ?? $this->countryOfDomain((string) $company->domain);

        if ($country === null) {
            return null;
        }

        $zones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $country);

        // A city that names one of the country's zones settles it outright.
        foreach ($zones as $zone) {
            $city = mb_strtolower(str_replace('_', ' ', (string) str($zone)->afterLast('/')));

            if ($location !== '' && str_contains($location, $city)) {
                return $zone;
            }
        }

        // Germany is Berlin and Büsingen, Spain is Madrid and the Canaries:
        // zones within an hour of each other all mean the same working day.
        $offsets = collect($zones)->mapWithKeys(fn (string $zone): array => [$zone => (new DateTimeZone($zone))->getOffset(now())]);

        if ($offsets->max() - $offsets->min() <= 3600) {
            $common = $offsets->mode()[0] ?? null;

            return (string) $offsets->filter(fn (int $offset): bool => $offset === $common)->keys()->first();
        }

        // ponytail: "Austin, Texas" lands on New York, two hours early; a
        // state/province map is the upgrade if US and Canadian volume matters.
        return self::MAIN_ZONES[$country] ?? null;
    }

    private function countryIn(string $location): ?string
    {
        if ($location === '') {
            return null;
        }

        foreach (self::countries() as $name => $code) {
            if (preg_match('/\b'.preg_quote($name, '/').'\b/u', $location)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * `.be` is Belgium; `.com` is nowhere in particular.
     */
    private function countryOfDomain(string $domain): ?string
    {
        $tld = mb_strtoupper((string) str($domain)->afterLast('.'));
        $tld = $tld === 'UK' ? 'GB' : $tld;

        return strlen($tld) === 2 && in_array($tld, self::countries(), true) ? $tld : null;
    }

    /**
     * Longest names first, so "United States" wins over a shorter name it
     * happens to contain.
     *
     * @return array<string, string>
     */
    private static function countries(): array
    {
        if (self::$countries !== null) {
            return self::$countries;
        }

        $codes = collect(DateTimeZone::listIdentifiers())
            ->map(function (string $zone): string {
                $location = (new DateTimeZone($zone))->getLocation();

                return $location === false ? '' : $location['country_code'];
            })
            ->filter(fn (string $code): bool => strlen($code) === 2)
            ->unique();

        $names = [];

        foreach ($codes as $code) {
            foreach (self::LANGUAGES as $language) {
                $name = mb_strtolower((string) Locale::getDisplayRegion('-'.$code, $language));

                if ($name !== '' && $name !== mb_strtolower($code)) {
                    $names[$name] = $code;
                }
            }
        }

        uksort($names, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return self::$countries = $names;
    }
}
