<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * A `min`/`max` on a date column comes back as a raw database string, never a
 * cast attribute, because it was never read through the model: `withMin`,
 * `withCount`'s cousins, and a raw `->min()` call all do this. Every other
 * date a screen shows is a `Carbon` instance, so this is what makes the two
 * reach the same formatter instead of drifting into two date shapes.
 */
class AggregateDate
{
    public static function parse(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }
}
