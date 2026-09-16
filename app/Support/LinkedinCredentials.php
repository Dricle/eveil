<?php

namespace App\Support;

/**
 * The instance's own LinkedIn Developer App: client id/secret, not a user's
 * OAuth token (that lives per-organization on `LinkedinAccount`).
 *
 * Instance-scope, same reasoning as `App\Ai\ProviderCredentials`: self-hosted
 * needs its own LinkedIn Developer App plus `w_member_social`/OIDC product
 * approval, so this is bring-your-own-key like #13's third-party providers,
 * managed by the superadmin, never an organization admin.
 */
class LinkedinCredentials
{
    public function __construct(private Settings $settings) {}

    public function clientId(): ?string
    {
        return $this->settings->get('linkedin.client_id');
    }

    public function clientSecret(): ?string
    {
        return $this->settings->secret('linkedin.client_secret');
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== null && $this->settings->hasSecret('linkedin.client_secret');
    }

    public function save(string $clientId, string $clientSecret): void
    {
        $this->settings->set('linkedin.client_id', $clientId);
        $this->settings->set('linkedin.client_secret', $clientSecret, encrypted: true);
    }

    public function forget(): void
    {
        $this->settings->forget('linkedin.client_id');
        $this->settings->forget('linkedin.client_secret');
    }

    /**
     * A SECOND LinkedIn Developer App, for the Community Management API's
     * restricted `r_member_social_feed` (post-performance polling).
     * LinkedIn does not allow that product to live on the same app as Share
     * on LinkedIn, so this is a genuinely separate registration - its own
     * client id/secret, its own review wait - never a wider scope on the
     * app above. Entirely optional: nothing else in the product depends on
     * it being configured.
     */
    public function statsClientId(): ?string
    {
        return $this->settings->get('linkedin.stats_client_id');
    }

    public function statsClientSecret(): ?string
    {
        return $this->settings->secret('linkedin.stats_client_secret');
    }

    public function isStatsConfigured(): bool
    {
        return $this->statsClientId() !== null && $this->settings->hasSecret('linkedin.stats_client_secret');
    }

    public function saveStats(string $clientId, string $clientSecret): void
    {
        $this->settings->set('linkedin.stats_client_id', $clientId);
        $this->settings->set('linkedin.stats_client_secret', $clientSecret, encrypted: true);
    }

    public function forgetStats(): void
    {
        $this->settings->forget('linkedin.stats_client_id');
        $this->settings->forget('linkedin.stats_client_secret');
    }
}
