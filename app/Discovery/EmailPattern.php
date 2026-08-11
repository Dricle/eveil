<?php

namespace App\Discovery;

/**
 * Turns one known address into the others (story 5.4). This is how Hunter and
 * every similar tool works, and it is the difference between "we found the
 * owner's name" and "we can write to the owner".
 *
 * Everything produced here is a guess, so it is stored as `inferred` and never
 * sent to until verification has had a look (ADR-007).
 */
class EmailPattern
{
    /**
     * Reads the shape out of an address we know belongs to a named person.
     */
    public static function detect(string $email, string $firstName, string $lastName): ?string
    {
        $local = mb_strtolower(strtok($email, '@') ?: '');
        $first = self::slug($firstName);
        $last = self::slug($lastName);

        if ($local === '' || $first === '' || $last === '') {
            return null;
        }

        return match ($local) {
            "{$first}.{$last}" => 'first.last',
            "{$first}{$last}" => 'firstlast',
            "{$first}_{$last}" => 'first_last',
            "{$first[0]}.{$last}" => 'f.last',
            "{$first[0]}{$last}" => 'flast',
            "{$last}.{$first}" => 'last.first',
            $first => 'first',
            $last => 'last',
            default => null,
        };
    }

    public static function apply(string $pattern, string $firstName, string $lastName, string $domain): ?string
    {
        $first = self::slug($firstName);
        $last = self::slug($lastName);

        if ($first === '' || $last === '' || $domain === '') {
            return null;
        }

        $local = match ($pattern) {
            'first.last' => "{$first}.{$last}",
            'firstlast' => "{$first}{$last}",
            'first_last' => "{$first}_{$last}",
            'f.last' => "{$first[0]}.{$last}",
            'flast' => "{$first[0]}{$last}",
            'last.first' => "{$last}.{$first}",
            'first' => $first,
            'last' => $last,
            default => null,
        };

        return $local === null ? null : "{$local}@{$domain}";
    }

    /**
     * Accents are the norm in this market and never appear in a local part.
     */
    private static function slug(string $name): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name;

        return mb_strtolower((string) preg_replace('/[^a-zA-Z]/', '', $ascii));
    }
}
