<?php

namespace App\Enums;

/**
 * How an index host behaved the last time we tried to read it. Learned by
 * trying; `Blocked` is the one that saves money, since a host behind bot
 * protection must never be paid for twice.
 */
enum HarvestStatus: string
{
    /** Structured data on the page. Free to read. */
    case JsonLd = 'jsonld';

    /** No structured data; the model had to read it. Costs money per page. */
    case Llm = 'llm';

    /** Bot protection or an outright refusal. Do not try again. */
    case Blocked = 'blocked';

    /**
     * Fetched, but the server sent a shell: almost no text to read. The only
     * status a headless renderer would actually fix, which is why it is kept
     * apart from "read fine, had nothing on it".
     */
    case JsOnly = 'js_only';

    /** Read perfectly well and simply listed no businesses. Not a rendering problem. */
    case NoListing = 'no_listing';

    public function worthRetrying(): bool
    {
        return $this !== self::Blocked;
    }

    /**
     * Whether a headless renderer would change the outcome. Only `JsOnly`
     * would: `Blocked` is bot protection, which fingerprints a browser too, and
     * `NoListing` means the page was read and had nothing on it.
     */
    public function needsRendering(): bool
    {
        return $this === self::JsOnly;
    }
}
