<?php

namespace App\Services\Composio;

use App\Enums\ComposioToolkit;
use App\Models\Integration;
use App\Models\Organization;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Composio's REST API, for every `ComposioToolkit`: open a connection link, check
 * what came back, run a tool as a connected account, stage a file a tool
 * takes as input, revoke a connection. Plain HTTP, no SDK: Composio ships
 * none for PHP, and five endpoints do not earn a dependency.
 *
 * Every connection uses Composio's own managed OAuth app for that service,
 * already approved by it, which is the whole point.
 *
 * Every call goes through the organization's OWN Composio key: its
 * connections live in its own Composio project, under its own quota, and no
 * key in Eveil ever reaches another organization's. Within that project, one
 * Composio user per organization (`userId()`), whatever the toolkit.
 */
class ComposioClient
{
    private const API = 'https://backend.composio.dev/api/v3.1';

    /**
     * A link the user follows to authorize the service on Composio's page,
     * and the connected account it will fill in once they do.
     *
     * @return array{redirect_url: string, connected_account_id: string}
     */
    public function connectionLink(ComposioToolkit $toolkit, Organization $organization, string $callbackUrl): array
    {
        $response = $this->request($organization)->post(self::API.'/connected_accounts/link', [
            'auth_config_id' => $this->authConfigId($toolkit, $organization),
            'user_id' => $this->userId($organization->id),
            'callback_url' => $callbackUrl,
        ]);

        $this->ensureSuccessful($response->successful(), $response->status(), $response->body());

        return [
            'redirect_url' => (string) $response->json('redirect_url'),
            'connected_account_id' => (string) $response->json('connected_account_id'),
        ];
    }

    /**
     * `ACTIVE` once the user authorized, something else (`INITIATED`,
     * `FAILED`, `EXPIRED`) otherwise.
     */
    public function connectionStatus(Integration $integration): string
    {
        $response = $this->request($integration->organization)->get(self::API."/connected_accounts/{$integration->connected_account_id}");

        $this->ensureSuccessful($response->successful(), $response->status(), $response->body());

        return (string) $response->json('status');
    }

    /**
     * Revokes the connection on Composio's side. Best effort: an account
     * already gone there is exactly what the caller wanted.
     */
    public function deleteConnection(Integration $integration): void
    {
        $this->request($integration->organization)->delete(self::API."/connected_accounts/{$integration->connected_account_id}");
    }

    /**
     * Runs one tool as the connected account and returns what it answered.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(Integration $integration, string $tool, array $arguments): array
    {
        $response = $this->request($integration->organization)->post(self::API."/tools/execute/{$tool}", [
            'connected_account_id' => $integration->connected_account_id,
            'user_id' => $this->userId($integration->organization_id),
            'arguments' => $arguments,
        ]);

        if (! $response->successful() || $response->json('successful') !== true) {
            $error = $response->json('error');

            throw new RuntimeException("Composio {$tool} failed: ".(is_string($error) ? $error : json_encode($error ?? $response->body())));
        }

        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }

    /**
     * Stages a file for a tool that takes one as input, and returns the
     * descriptor that tool expects in place of the file itself. The bytes go
     * straight to Composio's storage, never through its API.
     *
     * @return array{name: string, mimetype: string, s3key: string}
     */
    public function uploadFile(Integration $integration, string $tool, string $filename, string $mimetype, string $bytes): array
    {
        $response = $this->request($integration->organization)->post(self::API.'/files/upload/request', [
            'toolkit_slug' => $integration->toolkit->value,
            'tool_slug' => $tool,
            'filename' => $filename,
            'mimetype' => $mimetype,
            'md5' => md5($bytes),
        ]);

        $this->ensureSuccessful($response->successful(), $response->status(), $response->body());

        $headers = $response->json('metadata.storage_backend') === 'azure_blob_storage' ? ['x-ms-blob-type' => 'BlockBlob'] : [];

        $upload = Http::withHeaders($headers)
            ->withBody($bytes, $mimetype)
            ->put((string) $response->json('new_presigned_url'));

        $this->ensureSuccessful($upload->successful(), $upload->status(), $upload->body());

        return ['name' => $filename, 'mimetype' => $mimetype, 's3key' => (string) $response->json('key')];
    }

    /**
     * The toolkit's auth config on Composio's managed app, created the first
     * time anyone connects it and remembered after that.
     */
    private function authConfigId(ComposioToolkit $toolkit, Organization $organization): string
    {
        $id = $organization->composio_auth_configs[$toolkit->value] ?? null;

        if ($id !== null) {
            return $id;
        }

        $response = $this->request($organization)->post(self::API.'/auth_configs', [
            'toolkit' => ['slug' => $toolkit->value],
            'auth_config' => ['type' => 'use_composio_managed_auth', 'credentials' => (object) []],
        ]);

        $this->ensureSuccessful($response->successful(), $response->status(), $response->body());

        $id = (string) $response->json('auth_config.id');
        $organization->update(['composio_auth_configs' => [...$organization->composio_auth_configs ?? [], $toolkit->value => $id]]);

        return $id;
    }

    private function userId(int $organizationId): string
    {
        return "organization-{$organizationId}";
    }

    private function request(Organization $organization): PendingRequest
    {
        $apiKey = $organization->composio_api_key;

        if ($apiKey === null) {
            throw new RuntimeException('This organization has no Composio API key yet: add one under Settings, Composio.');
        }

        return Http::withHeaders(['x-api-key' => $apiKey])->acceptJson()->timeout(60);
    }

    private function ensureSuccessful(bool $successful, int $status, string $body): void
    {
        if (! $successful) {
            throw new RuntimeException("Composio refused the request: HTTP {$status} {$body}");
        }
    }
}
