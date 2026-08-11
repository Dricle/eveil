<?php

namespace App\Discovery;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;

/**
 * Turns a page into the text an LLM should read, and pulls its links.
 *
 * Deliberately dependency-free: DOMDocument plus a strip list beats adding a
 * markdown converter for what amounts to "drop the chrome, keep the prose".
 */
class HtmlText
{
    private const STRIP = ['script', 'style', 'noscript', 'svg', 'nav', 'footer', 'header', 'form', 'iframe'];

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
            text: $this->text($document, $xpath),
            links: $this->links($xpath, $baseUrl),
        );
    }

    private function load(string $html): ?DOMDocument
    {
        if (trim($html) === '') {
            return null;
        }

        $document = new DOMDocument;

        // Without the encoding hint, DOMDocument assumes ISO-8859-1 and mangles
        // every accented character — which is most of our target market.
        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        return $loaded ? $document : null;
    }

    private function text(DOMDocument $document, DOMXPath $xpath): string
    {
        foreach (self::STRIP as $tag) {
            /** @var array<int, DOMElement> $nodes */
            $nodes = iterator_to_array($document->getElementsByTagName($tag));

            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $body = $this->first($xpath, '//body');
        $text = $body instanceof \DOMNode ? $body->textContent : $document->textContent;

        // Collapse the whitespace HTML sources are full of: it is a sizeable
        // share of the tokens and carries nothing.
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace('/\s*\n\s*/u', "\n", $text);

        return trim((string) preg_replace('/\n{3,}/u', "\n\n", $text));
    }

    private function title(DOMXPath $xpath): ?string
    {
        $node = $this->first($xpath, '//title');
        $title = $node instanceof \DOMNode ? trim($node->textContent) : '';

        return $title === '' ? null : $title;
    }

    private function language(DOMXPath $xpath): ?string
    {
        $node = $this->first($xpath, '//html/@lang');
        $lang = $node instanceof \DOMNode ? trim($node->nodeValue ?? '') : '';

        return $lang === '' ? null : mb_strtolower(mb_substr($lang, 0, 2));
    }

    /**
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

    private function first(DOMXPath $xpath, string $expression): ?\DOMNode
    {
        $node = $this->query($xpath, $expression)->item(0);

        return $node instanceof \DOMNode ? $node : null;
    }

    /**
     * `DOMXPath::query()` returns false on a malformed expression. Ours are
     * literals, so that cannot happen — but the type says it can.
     *
     * @return DOMNodeList<\DOMNode>
     */
    private function query(DOMXPath $xpath, string $expression): DOMNodeList
    {
        $result = $xpath->query($expression);

        /** @var DOMNodeList<\DOMNode> */
        return $result === false ? new DOMNodeList : $result;
    }
}
