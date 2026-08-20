<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMText;
use DOMXPath;

/**
 * Turns a page into the markdown an LLM should read, and pulls its links.
 *
 * Deliberately dependency-free: DOMDocument plus a tag map beats adding a
 * markdown converter for what amounts to "drop the chrome, keep the meaning".
 *
 * Markdown rather than flat text, decided 2026-08-12, because flat
 * text throws away anchor labels: a directory listing came out as two hundred
 * business names on one side and two hundred URLs on the other, with nothing
 * pairing them. `[Acme Plumbing](/company/acme-plumbing-4412)` keeps them
 * together. Same reason `mailto:` and `tel:` survive here while `Url::resolve`
 * drops them. They are not links to follow, they are the contact details, and
 * an address that only ever appears in an href used to be invisible.
 */
class HtmlText
{
    /** Chrome and machinery: never carries meaning, always carries tokens. */
    private const SKIP = ['script', 'style', 'noscript', 'svg', 'iframe', 'form', 'template', 'select', 'button'];

    /**
     * `nav`, `header` and `footer` are deliberately NOT skipped. They are the
     * obvious candidates, until you notice pagination lives in `nav`, so
     * dropping it deletes "next page" and a listing harvest stops at page one.
     * The extra tokens are a rounding error against that.
     */
    private const BLOCK = [
        'p', 'div', 'section', 'article', 'main', 'aside', 'nav', 'header', 'footer',
        'ul', 'ol', 'dl', 'dt', 'dd', 'table', 'tr', 'blockquote', 'pre', 'address', 'figure',
    ];

    private const HEADINGS = ['h1' => 1, 'h2' => 2, 'h3' => 3, 'h4' => 4, 'h5' => 5, 'h6' => 6];

    /** Kept verbatim: they are the payload, not a link to crawl. */
    private const CONTACT_SCHEMES = ['mailto:', 'tel:'];

    public function parse(string $html, string $baseUrl): ParsedPage
    {
        $document = $this->load($html);

        if ($document === null) {
            return new ParsedPage($baseUrl);
        }

        $xpath = new DOMXPath($document);

        return new ParsedPage(
            url: $baseUrl,
            title: $this->title($xpath),
            language: $this->language($xpath),
            text: $this->markdown($xpath, $baseUrl),
            links: $this->links($xpath, $baseUrl),
        );
    }

    /**
     * The parsed document, for the callers that need the markup itself rather
     * than its prose, `JsonLd` reads `script` tags, which `parse()` strips.
     * Public so the encoding fix below lives in exactly one place.
     */
    public function document(string $html): ?DOMDocument
    {
        return $this->load($html);
    }

    /**
     * The next page of a paginated listing, or null at the end of it.
     *
     * Three signals, most reliable first. Anything cleverer: inferring
     * `?page=N+1`, following the highest numbered link. Invents URLs that
     * either 404 or loop, and a harvest that loops burns the page budget on
     * one directory.
     */
    public function next(string $html, string $baseUrl): ?string
    {
        $document = $this->load($html);

        if ($document === null) {
            return null;
        }

        $xpath = new DOMXPath($document);

        foreach (['//link[@rel="next"]/@href', '//a[@rel="next"]/@href'] as $expression) {
            $node = $this->first($xpath, $expression);

            if ($node instanceof DOMNode) {
                return Url::resolve(trim($node->nodeValue ?? ''), $baseUrl);
            }
        }

        foreach ($this->query($xpath, '//a[@href]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $label = mb_strtolower(trim($node->textContent).' '.$node->getAttribute('aria-label'));

            if (preg_match('/\b(next|suivante?|volgende|weiter|siguiente)\b|›|»|→/u', $label) === 1) {
                $resolved = Url::resolve($node->getAttribute('href'), $baseUrl);

                if ($resolved !== null && $resolved !== Url::normalize($baseUrl)) {
                    return $resolved;
                }
            }
        }

        return null;
    }

    private function load(string $html): ?DOMDocument
    {
        if (trim($html) === '') {
            return null;
        }

        $document = new DOMDocument;

        // Without the encoding hint, DOMDocument assumes ISO-8859-1 and mangles
        // every accented character. Which is most of our target market.
        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        return $loaded ? $document : null;
    }

    private function markdown(DOMXPath $xpath, string $baseUrl): string
    {
        $body = $this->first($xpath, '//body');

        if (! $body instanceof DOMNode) {
            return '';
        }

        return $this->tidy($this->render($body, $baseUrl));
    }

    /**
     * Walks the tree once, emitting markdown. Block elements are wrapped in
     * newlines and everything else stays inline, which is enough structure for
     * a model to tell a list of businesses from a paragraph about one.
     */
    private function render(DOMNode $node, string $baseUrl): string
    {
        if ($node instanceof DOMText) {
            return (string) preg_replace('/\s+/u', ' ', $node->wholeText);
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = mb_strtolower($node->nodeName);

        if (in_array($tag, self::SKIP, true)) {
            return '';
        }

        if ($tag === 'br') {
            return "\n";
        }

        if ($tag === 'hr') {
            return "\n\n";
        }

        if ($tag === 'a') {
            return $this->link($node, $baseUrl);
        }

        $inner = $this->children($node, $baseUrl);

        if (isset(self::HEADINGS[$tag])) {
            $heading = trim($inner);

            return $heading === '' ? '' : "\n\n".str_repeat('#', self::HEADINGS[$tag])." {$heading}\n\n";
        }

        if ($tag === 'li') {
            $item = trim($inner);

            return $item === '' ? '' : "\n- {$item}";
        }

        // Cells joined by a pipe so a row of a listing table stays one line and
        // stays readable; `tr` is a block, so rows still break.
        if ($tag === 'td' || $tag === 'th') {
            $cell = trim($inner);

            return $cell === '' ? '' : "{$cell} | ";
        }

        if (in_array($tag, self::BLOCK, true)) {
            return "\n{$inner}\n";
        }

        return $inner;
    }

    private function children(DOMNode $node, string $baseUrl): string
    {
        $out = '';

        foreach ($node->childNodes as $child) {
            $out .= $this->render($child, $baseUrl);
        }

        return $out;
    }

    /**
     * A link with no label is navigation furniture (an icon, a spacer) and is
     * dropped; a label identical to its URL is emitted once rather than twice.
     */
    private function link(DOMElement $node, string $baseUrl): string
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', $this->children($node, $baseUrl)));
        $href = trim($node->getAttribute('href'));

        if ($label === '') {
            return '';
        }

        foreach (self::CONTACT_SCHEMES as $scheme) {
            if (str_starts_with(mb_strtolower($href), $scheme)) {
                return "[{$label}]({$href})";
            }
        }

        $resolved = Url::resolve($href, $baseUrl);

        if ($resolved === null || $resolved === $label) {
            return $label;
        }

        return "[{$label}]({$resolved})";
    }

    /**
     * Collapses the whitespace HTML sources are full of. A sizeable share of
     * the tokens, carrying nothing.
     */
    private function tidy(string $markdown): string
    {
        $markdown = (string) preg_replace('/[ \t]+/u', ' ', $markdown);
        $markdown = (string) preg_replace('/ *\n */u', "\n", $markdown);
        $markdown = (string) preg_replace('/ *\| *\n/u', "\n", $markdown);
        $markdown = (string) preg_replace('/\n{3,}/u', "\n\n", $markdown);

        return trim($markdown);
    }

    private function title(DOMXPath $xpath): ?string
    {
        $node = $this->first($xpath, '//title');
        $title = $node instanceof DOMNode ? trim($node->textContent) : '';

        return $title === '' ? null : $title;
    }

    private function language(DOMXPath $xpath): ?string
    {
        $node = $this->first($xpath, '//html/@lang');
        $lang = $node instanceof DOMNode ? trim($node->nodeValue ?? '') : '';

        return $lang === '' ? null : mb_strtolower(mb_substr($lang, 0, 2));
    }

    /**
     * Crawlable links only, `Url::resolve` drops `mailto:`, `tel:` and
     * fragments, which is right here and wrong in the markdown.
     *
     * @return array<int, string>
     */
    private function links(DOMXPath $xpath, string $baseUrl): array
    {
        $links = [];

        foreach ($this->query($xpath, '//a[@href]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $resolved = Url::resolve($node->getAttribute('href'), $baseUrl);

            if ($resolved !== null) {
                $links[$resolved] = true;
            }
        }

        return array_keys($links);
    }

    private function first(DOMXPath $xpath, string $expression): ?DOMNode
    {
        $node = $this->query($xpath, $expression)->item(0);

        return $node instanceof DOMNode ? $node : null;
    }

    /**
     * `DOMXPath::query()` returns false on a malformed expression. Ours are
     * literals, so that cannot happen, but the type says it can.
     *
     * @return DOMNodeList<DOMNode>
     */
    private function query(DOMXPath $xpath, string $expression): DOMNodeList
    {
        $result = $xpath->query($expression);

        /** @var DOMNodeList<DOMNode> */
        return $result === false ? new DOMNodeList : $result;
    }
}
