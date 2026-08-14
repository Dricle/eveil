<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Fills `slug` from another attribute when it is empty, and keeps it unique by
 * appending a counter. Two organizations named "Acme Tools" is ordinary and the
 * column is unique, so the de-duplication belongs here rather than in every
 * call site that happens to create one.
 *
 * A slug is only ever generated when it is missing: renaming a record leaves
 * its slug alone, because slugs end up in URLs and in what people bookmark.
 * Pass a slug explicitly to choose one.
 *
 * @phpstan-require-extends Model
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function (self $model): void {
            if (filled($model->getAttribute('slug'))) {
                return;
            }

            $model->setAttribute('slug', $model->generateSlug());
        });
    }

    /**
     * The attribute a slug is derived from. Override on the model to use
     * another one.
     */
    protected function slugSource(): string
    {
        return 'name';
    }

    protected function generateSlug(): string
    {
        $base = Str::slug((string) $this->getAttribute($this->slugSource()))
            ?: Str::lower(class_basename($this));

        $slug = $base;
        $suffix = 1;

        while ($this->slugExists($slug)) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        return static::query()
            ->where('slug', $slug)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists();
    }
}
