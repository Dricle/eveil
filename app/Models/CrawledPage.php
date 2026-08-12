<?php

namespace App\Models;

use Database\Factories\CrawledPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The one shared table. It holds public web content and nothing else
 * — never a page behind a login — which is what makes sharing it across tenants
 * safe. Companies and leads stay scoped to their project.
 *
 * @property int $id
 * @property string $url_hash
 * @property string $url
 * @property int|null $status_code
 * @property string|null $content_type
 * @property string|null $language
 * @property string|null $content
 * @property Carbon $fetched_at
 * @property Carbon $expires_at
 */
#[Fillable(['url_hash', 'url', 'status_code', 'content_type', 'language', 'content', 'fetched_at', 'expires_at'])]
class CrawledPage extends Model
{
    /** @use HasFactory<CrawledPageFactory> */
    use HasFactory;

    public $timestamps = false;

    public static function hashFor(string $url): string
    {
        return hash('sha256', $url);
    }

    public function isFresh(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
