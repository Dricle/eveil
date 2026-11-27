<?php

namespace App\Http\Resources;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * No app password: write-only from the UI's point of view.
 *
 * @mixin SocialAccount
 */
class SocialAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'handle' => $this->handle,
            'display_name' => $this->display_name,
            // LinkedIn issues no refresh token to most apps: past its 60 days
            // the token is dead even before a publish finds out.
            'status' => $this->linkedinTokenExpired() ? SocialAccountStatus::Expired->value : $this->status->value,
            'linkedin_token_expires_at' => $this->platform === SocialPlatform::Linkedin ? $this->secret_expires_at?->toIso8601String() : null,
            'last_error' => $this->last_error,
            // LinkedIn only: whether its separate performance-polling app is
            // connected, the one way Eveil can read its posts' numbers.
            'has_stats_access' => $this->hasStatsAccess(),
            // Instagram only: its two ways in, and which one publishes right
            // now. The organization's own Meta app always wins while its
            // token is alive; Composio is the fallback.
            'via_meta_app' => $this->platform_app_id !== null,
            'via_composio' => $this->composio_connection_id !== null,
            'publishes_through' => match (true) {
                $this->publishesThroughOwnApp() => 'meta',
                $this->composio_connection_id !== null => 'composio',
                default => null,
            },
            'meta_token_expires_at' => $this->platform_app_id === null ? null : $this->secret_expires_at?->toIso8601String(),
            'projects' => $this->whenLoaded('projects', fn () => $this->projects->pluck('id')->all()),
        ];
    }

    private function linkedinTokenExpired(): bool
    {
        return $this->platform === SocialPlatform::Linkedin && $this->secret_expires_at?->isPast() === true;
    }
}
