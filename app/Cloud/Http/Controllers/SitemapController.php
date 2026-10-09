<?php

namespace App\Cloud\Http\Controllers;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Support\Settings;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * eveil.cloud's sitemap. The marketing pages are a fixed list; the articles
 * come from the same `blog.project_id` settings row BlogController reads, at
 * the same canonical address the article page declares. A self-hosted
 * instance has no public site to offer a crawler, so it 404s like every
 * other marketing route in `routes/web.php`.
 */
class SitemapController extends Controller
{
    /**
     * Only the pages a stranger can reach: the application itself lives
     * behind authentication and has nothing to index.
     */
    private const MARKETING_ROUTES = ['home', 'blog.index', 'contact', 'privacy', 'terms', 'data-retention'];

    public function __invoke(Settings $settings): Response
    {
        abort_unless(config('eveil.edition') === 'cloud', 404);

        /** @var list<array{loc: string, lastmod: string|null}> $urls */
        $urls = [];

        foreach (self::MARKETING_ROUTES as $name) {
            $urls[] = ['loc' => route($name), 'lastmod' => null];
        }

        $articles = Article::query()
            ->where('project_id', $settings->get('blog.project_id'))
            ->where('status', ArticleStatus::Published)
            ->latest('published_at')
            ->get();

        foreach ($articles as $article) {
            $urls[] = [
                'loc' => route('blog.show', [$article->id, Str::slug($article->title)]),
                'lastmod' => $article->updated_at?->toAtomString(),
            ];
        }

        return response()
            ->view('marketing.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
