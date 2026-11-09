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
 * city that names one of its zones ("Chicago", "Toronto"), and anything vaguer
 * returns null, which leaves the mail on the instance's own window, as before.
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
            $common = $offsets->countBy()->sortDesc()->keys()->first();

            return (string) $offsets->search($common);
        }

        // ponytail: zone city names and the one-hour rule only, so "Austin,
        // Texas" stays unresolved; a state/province map is the upgrade if US
        // and Canadian volume ever matters.
        return null;
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
            ->map(fn (string $zone): string => (string) (new DateTimeZone($zone))->getLocation()['country_code'])
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
