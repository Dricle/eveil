<?php

namespace App\Support;

use App\Enums\PlatformAppKind;
use App\Models\PlatformApp;

/**
 * The instance's own LinkedIn Developer Apps: client id/secret, not a user's
 * OAuth token (that lives per-organization on a LinkedIn `SocialAccount`).
 * Stored as instance rows of `platform_apps`, like every other OAuth app.
 *
 * Instance only, managed by the superadmin, never an organization admin:
 * self-hosted needs its own LinkedIn Developer App plus `w_member_social`/OIDC
 * product approval. Bring your own LinkedIn app would also need each
 * `SocialAccount` tied to the app that issued its token, so it waits until
 * someone asks for it.
 *
 * The SECOND app (`stats*`) is for the Community Management API's restricted
 * `r_member_postAnalytics` (post-performance polling). LinkedIn does not allow
 * that product to live on the same app as Share on LinkedIn, so it is a
 * genuinely separate registration with its own review wait, never a wider
 * scope on the first. Entirely optional: nothing else depends on it.
 */
class LinkedinCredentials
{
    public function clientId(): ?string
    {
        return $this->app(PlatformAppKind::LinkedinPosting)?->client_id;
    }

    public function clientSecret(): ?string
    {
        return $this->app(PlatformAppKind::LinkedinPosting)?->client_secret;
    }

    public function isConfigured(): bool
    {
        return $this->app(PlatformAppKind::LinkedinPosting) !== null;
    }

    public function save(string $clientId, string $clientSecret): void
    {
        $this->store(PlatformAppKind::LinkedinPosting, $clientId, $clientSecret);
    }

    public function forget(): void
    {
        $this->app(PlatformAppKind::LinkedinPosting)?->delete();
    }

    public function statsClientId(): ?string
    {
        return $this->app(PlatformAppKind::LinkedinStats)?->client_id;
    }

    public function statsClientSecret(): ?string
    {
        return $this->app(PlatformAppKind::LinkedinStats)?->client_secret;
    }

    public function isStatsConfigured(): bool
    {
        return $this->app(PlatformAppKind::LinkedinStats) !== null;
    }

    public function saveStats(string $clientId, string $clientSecret): void
    {
        $this->store(PlatformAppKind::LinkedinStats, $clientId, $clientSecret);
    }

    public function forgetStats(): void
    {
        $this->app(PlatformAppKind::LinkedinStats)?->delete();
    }

    private function app(PlatformAppKind $kind): ?PlatformApp
    {
        return PlatformApp::query()->whereNull('organization_id')->where('platform', $kind)->first();
    }

    private function store(PlatformAppKind $kind, string $clientId, string $clientSecret): void
    {
        PlatformApp::query()->updateOrCreate(
            ['organization_id' => null, 'platform' => $kind],
            ['client_id' => $clientId, 'client_secret' => $clientSecret],
        );
    }
}
