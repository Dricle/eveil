<?php

namespace App\Support;

/**
 * One readable page. A small object rather than an array shape: array shapes
 * are not covariant inside a Collection, so passing them between methods fights
 * static analysis for no benefit.
 */
readonly class ParsedPage
{
    /**
     * @param  array<int, string>  $links
     */
    public function __construct(
        public string $url,
        public ?string $title = null,
        public ?string $language = null,
        public string $text = '',
        public array $links = [],
    ) {}

    public function isEmpty(): bool
    {
        return trim($this->text) === '';
    }

    public function length(): int
    {
        return mb_strlen($this->text);
    }
}
