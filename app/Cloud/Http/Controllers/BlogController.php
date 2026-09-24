<?php

namespace App\Cloud\Http\Controllers;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Support\Settings;
use Illuminate\Contracts\View\View;

/**
 * eveil.cloud's own blog: the published articles of the project named by the
 * `blog.project_id` settings row, written with Eveil's own SEO feature. That
 * row is inserted by hand on eveil.cloud and exists nowhere else: no
 * migration, no screen, no env key. Without it the blog is simply empty.
 * A 404 on self-hosted, checked per request like the other marketing
 * routes in `routes/web.php`.
 */
class BlogController extends Controller
{
    public function index(Settings $settings): View
    {
        abort_unless(config('eveil.edition') === 'cloud', 404);

        return view('marketing.blog.index', [
            'articles' => Article::query()
                ->where('project_id', $settings->get('blog.project_id'))
                ->where('status', ArticleStatus::Published)
                ->latest('published_at')
                ->get(),
        ]);
    }

    /**
     * `{slug}` is only there for readers and search engines: the id finds
     * the article, so a retitled one keeps working at its old address.
     */
    public function show(Settings $settings, int $article): View
    {
        abort_unless(config('eveil.edition') === 'cloud', 404);

        return view('marketing.blog.show', [
            'article' => Article::query()
                ->where('project_id', $settings->get('blog.project_id'))
                ->where('status', ArticleStatus::Published)
                ->findOrFail($article),
        ]);
    }
}
