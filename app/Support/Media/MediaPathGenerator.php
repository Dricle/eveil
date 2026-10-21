<?php

namespace App\Support\Media;

use LogicException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Stores every file as `{organization}/{feature}/{date}/{media id}/{file}`
 * instead of medialibrary's bare `{media id}/{file}`: one organization's
 * files sit under one folder, which is what exporting, auditing or deleting
 * an organization's files needs.
 *
 * The folder is computed once, when the file is first stored, and frozen in
 * the media's custom properties: medialibrary asks for the path again on
 * every read, and a folder recomputed from data that later changed (a
 * feature renamed, say) would point at a file that is not there.
 */
class MediaPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->directory($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->directory($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->directory($media).'/responsive-images/';
    }

    private function directory(Media $media): string
    {
        $directory = $media->getCustomProperty('directory');

        if (is_string($directory) && $directory !== '') {
            return $directory;
        }

        // Without global scopes: the media already belongs to this owner,
        // and the project scope would hide it whenever the request's current
        // project is another one.
        $owner = $media->relationLoaded('model') ? $media->model : $media->model()->withoutGlobalScopes()->first();

        if (! $owner instanceof StoresMediaInterface) {
            throw new LogicException($media->model_type.' keeps media but does not implement '.StoresMediaInterface::class.': nothing says which organization its files belong to.');
        }

        $directory = implode('/', [
            $owner->mediaOrganizationId(),
            $owner->mediaFeature(),
            ($media->created_at ?? now())->format('Y-m-d'),
            $media->getKey(),
        ]);

        $media->setCustomProperty('directory', $directory)->saveQuietly();

        return $directory;
    }
}
