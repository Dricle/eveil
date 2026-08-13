<?php

namespace App\Enums;

/**
 * How an index host behaved the last time we tried to read it. Learned by
 * trying; `Blocked` is the one that saves money, since a host behind bot
 * protection must never be paid for twice.
 */
enum HarvestStatus: string
{
    /** Structured data on the page — free to read. */
    case JsonLd = 'jsonld';

    /** No structured data; the model had to read it. Costs money per page. */
    case Llm = 'llm';

    /** Bot protection or an outright refusal. Do not try again. */
    case Blocked = 'blocked';

    /** Reachable but renders nothing server-side, and nothing was extracted. */
    case JsOnly = 'js_only';

    public function worthRetrying(): bool
    {
        return $this !== self::Blocked;
    }
}
