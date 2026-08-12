<?php

use App\Discovery\HtmlText;
use App\Discovery\ParsedPage;

function parsePage(string $html, string $url = 'https://example.be/annuaire/friteries'): ParsedPage
{
    return (new HtmlText)->parse($html, $url);
}

it('pairs a link with its label, which flat text could not', function () {
    // The whole point of emitting markdown. A listing page used to yield the names on one
    // side and the URLs on the other, with nothing joining them.
    $page = parsePage(<<<'HTML'
        <html><body><ul>
            <li><a href="/friterie/chez-marcel-4412">Chez Marcel</a></li>
            <li><a href="/friterie/belle-frite-881">Belle Frite</a></li>
        </ul></body></html>
        HTML);

    expect($page->text)
        ->toContain('- [Chez Marcel](https://example.be/friterie/chez-marcel-4412)')
        ->toContain('- [Belle Frite](https://example.be/friterie/belle-frite-881)');
});

it('keeps mailto and tel, which are contact details and not links to follow', function () {
    $page = parsePage('<html><body><a href="mailto:info@marcel.be">Contact</a> <a href="tel:+3281123456">Appelez-nous</a></body></html>');

    // An address reachable only through an href was invisible before: the model
    // never saw the raw HTML, only the extracted text.
    expect($page->text)
        ->toContain('[Contact](mailto:info@marcel.be)')
        ->toContain('[Appelez-nous](tel:+3281123456)')
        // …and they are still not crawlable.
        ->and($page->links)->toBe([]);
});

it('keeps pagination, which lives in nav', function () {
    $page = parsePage('<html><body><main>Résultats</main><nav><a href="?page=2">Page suivante</a></nav></body></html>');

    expect($page->text)->toContain('[Page suivante](https://example.be/annuaire/friteries?page=2)')
        ->and($page->links)->toContain('https://example.be/annuaire/friteries?page=2');
});

it('renders headings, lists and table rows as structure', function () {
    $page = parsePage(<<<'HTML'
        <html><body>
            <h1>Friteries à Namur</h1>
            <h2>Centre</h2>
            <table><tr><td>Chez Marcel</td><td>Rue Haute 4</td></tr></table>
        </body></html>
        HTML);

    expect($page->text)
        ->toContain('# Friteries à Namur')
        ->toContain('## Centre')
        ->toContain('Chez Marcel | Rue Haute 4');
});

it('drops chrome and unlabelled links', function () {
    $page = parsePage('<html><body><script>var a=1</script><style>p{}</style><a href="/x"><img src="i.png"></a><p>Texte</p></body></html>');

    expect($page->text)->toBe('Texte');
});

it('emits a bare url rather than a link that repeats itself', function () {
    $page = parsePage('<html><body><a href="https://marcel.be/">https://marcel.be/</a></body></html>');

    expect($page->text)->toBe('https://marcel.be/');
});

it('collapses whitespace instead of shipping it as tokens', function () {
    $page = parsePage("<html><body><div>  Un   \n\n  texte  </div>\n\n\n<div>Deux</div></body></html>");

    expect($page->text)->toBe("Un texte\n\nDeux");
});

it('resolves a query-only href against the base path, not its dirname', function () {
    // RFC 3986 §5.3. Getting this wrong sends every "page 2" link one directory
    // up, so a listing harvest reads page one forever.
    $page = parsePage('<html><body><a href="?page=2">2</a><a href="../contact">Contact</a></body></html>');

    expect($page->links)->toBe([
        'https://example.be/annuaire/friteries?page=2',
        'https://example.be/contact',
    ]);
});

it('still reads title and language, and survives junk', function () {
    $page = parsePage('<html lang="fr-BE"><head><title>Friterie</title></head><body><p>Bonjour</p></body></html>');

    expect($page->title)->toBe('Friterie')
        ->and($page->language)->toBe('fr')
        ->and(parsePage('')->isEmpty())->toBeTrue()
        ->and(parsePage('<<<not html')->text)->toBe('');
});
