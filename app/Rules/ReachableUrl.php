<?php

namespace App\Rules;

use App\Services\Discovery\PageFetcher;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An address nothing can be read at is an analysis that fails minutes later, in
 * a queue, where nobody is looking. Fetching once at save time moves that
 * failure back into the form, and the page lands in the crawl cache, so the
 * analysis that follows does not pay for it a second time.
 */
class ReachableUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Resolved here rather than injected: validation rules are built with
        // `new` at the call site, so a constructor gets nothing.
        if (! is_string($value) || app(PageFetcher::class)->fetch($value) === null) {
            $fail('Nothing could be read at this address. Check it, or whether the site blocks crawlers.');
        }
    }
}
