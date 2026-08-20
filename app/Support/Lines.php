<?php

namespace App\Support;

/**
 * The list fields of the knowledge base and of a target profile travel one item
 * per line: a textarea is what people paste into, and a tag editor would be a
 * component to maintain for no gain.
 */
class Lines
{
    /**
     * Anything that is not a string is an empty list: a cleared textarea
     * arrives as null, because `ConvertEmptyStringsToNull` runs long before a
     * form request sees it.
     *
     * @return array<int, string>
     */
    public static function split(mixed $text): array
    {
        if (! is_string($text)) {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), preg_split('/\R/', $text) ?: []),
            fn (string $line): bool => $line !== '',
        ));
    }
}
