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
}
