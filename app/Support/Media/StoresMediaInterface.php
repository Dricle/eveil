<?php

namespace App\Support\Media;

/**
 * A model that keeps files through medialibrary says where they belong, so
 * they are stored as `{organization}/{feature}/{date}/{media id}/{file}`
 * (`MediaPathGenerator`): one organization's files under one folder, each
 * feature's under its own.
 */
interface StoresMediaInterface
{
    /**
     * The organization the files belong to.
     */
    public function mediaOrganizationId(): int;

    /**
     * The feature they belong to, as a folder name: `social-posts`, `ads`.
     */
    public function mediaFeature(): string;
}
