<?php

namespace App\Services\Discovery;

/**
 * Turns one known address into the others. This is how Hunter and every similar
 * tool works, and it is the difference between "we found the owner's name" and
 * "we can write to the owner".
 *
 * Everything produced here is a guess, so it is stored as `inferred` and never
 * sent to until verification has had a look.
 *
 * There is no list of shapes. There was — eight of them, matched one by one —
 * and it silently failed on `first-last`, `last-first`, `f_last`, `firstl` and
 * every other combination nobody had written down. A missing shape is not a
 * quiet miss either: `detect()` returns null, the site's real convention is
 * lost, and the fallback guesses a different one that bounces. Bounces are the
 * fastest way to wreck a sending domain.
 *
 * So a shape is PARSED rather than recognised: two parts drawn from the name,
 * optionally separated. `first.last`, `flast`, `l-first` and `last_f` all fall
 * out of the same rule, and so does whatever convention we have not met.
 */
class EmailPattern
{
    /**
     * The pieces of a person's name an address can be built from, longest
     * first so `first` is preferred over `f` when both would fit.
     */
    private const PARTS = ['first', 'last', 'f', 'l'];

    /** What sites put between the two pieces, `''` meaning nothing at all. */
    private const SEPARATORS = ['.', '_', '-', ''];

    /**
     * Reads the shape out of an address we know belongs to a named person.
     */
    public static function detect(string $email, string $firstName, string $lastName): ?string
    {
        $local = mb_strtolower(strtok($email, '@') ?: '');
        $pieces = self::pieces($firstName, $lastName);

        if ($local === '' || $pieces === []) {
            return null;
        }

        foreach (self::candidates($pieces) as $pattern => $rendered) {
            if ($rendered === $local) {
                return $pattern;
            }
        }

        return null;
    }

    public static function apply(string $pattern, string $firstName, string $lastName, string $domain): ?string
    {
        $pieces = self::pieces($firstName, $lastName);

        if ($pieces === [] || $domain === '') {
            return null;
        }

        $local = self::candidates($pieces)[$pattern] ?? null;

        return $local === null ? null : "{$local}@{$domain}";
    }

    /**
     * Every shape this person's name can produce, as pattern => local part.
     *
     * Built rather than listed, which is the whole point: eight hand-written
     * cases became thirty-odd without anyone having to think of them, and a
     * convention we have never seen is recognised the first time it appears.
     *
     * @param  array<string, string>  $pieces
     * @return array<string, string>
     */
    private static function candidates(array $pieces): array
    {
        $out = [];

        // A single piece on its own: `marie@`, `dupont@`.
        foreach ($pieces as $name => $value) {
            $out[$name] = $value;
        }

        foreach ($pieces as $leftName => $left) {
            foreach ($pieces as $rightName => $right) {
                // Not the same piece twice, and never two bare initials: `md@`
                // matches far too many people to guess anyone else's address from.
                if ($leftName === $rightName || (mb_strlen($left) === 1 && mb_strlen($right) === 1)) {
                    continue;
                }

                foreach (self::SEPARATORS as $separator) {
                    $out["{$leftName}{$separator}{$rightName}"] ??= "{$left}{$separator}{$right}";
                }
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function pieces(string $firstName, string $lastName): array
    {
        $first = self::slug($firstName);
        $last = self::slug($lastName);

        if ($first === '' || $last === '') {
            return [];
        }

        $pieces = [];

        foreach (self::PARTS as $part) {
            $pieces[$part] = match ($part) {
                'first' => $first,
                'last' => $last,
                'f' => mb_substr($first, 0, 1),
                'l' => mb_substr($last, 0, 1),
            };
        }

        return $pieces;
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
