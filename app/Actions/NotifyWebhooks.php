<?php

namespace App\Actions;

use App\Enums\OutreachStatus;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Project;
use App\Models\Webhook;
use Illuminate\Support\Str;

/**
 * Telling a project's webhooks that something happened. Each subscribed
 * endpoint gets its own queued delivery, so one slow CRM never holds up the
 * reply read or the status click that caused the event.
 *
 * The contact and company carry the same field names as the public API, so a
 * flow that reads one can look the other up without a mapping table.
 */
class NotifyWebhooks
{
    public function replyReceived(Message $reply): void
    {
        $lead = $reply->lead;

        $this->dispatch($lead->project, WebhookEvent::ReplyReceived, [
            'contact' => $this->contact($lead),
            'company' => $lead->company === null ? null : $this->company($lead->company),
            'reply' => [
                'id' => $reply->id,
                'subject' => $reply->subject,
                'body' => $reply->body,
                'classification' => $reply->classification?->value,
                'received_at' => $reply->received_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * One event per status write, never per person at a company: a company
     * marked won says so once, with `contact` null, rather than once for each
     * address found at it.
     */
    public function statusChanged(Company $company, ?Lead $lead, ?OutreachStatus $previous): void
    {
        $this->dispatch($company->project, WebhookEvent::StatusChanged, [
            'contact' => $lead === null ? null : $this->contact($lead),
            'company' => $this->company($company),
            'status' => ($lead ?? $company)->status->value,
            'previous_status' => $previous?->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dispatch(Project $project, WebhookEvent $event, array $data): void
    {
        // Explicit project rather than the scope: this runs from jobs and
        // commands too, where no current project may be set.
        $webhooks = Webhook::query()
            ->where('project_id', $project->id)
            ->whereNull('disabled_at')
            ->whereJsonContains('events', $event->value)
            ->get();

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = [
            'id' => (string) Str::uuid(),
            'event' => $event->value,
            'created_at' => now()->toIso8601String(),
            'project' => ['slug' => $project->slug, 'name' => $project->name],
            ...$data,
        ];

        $webhooks->each(fn (Webhook $webhook) => DeliverWebhook::dispatch($webhook, $payload));
    }

    /**
     * @return array<string, mixed>
     */
    private function contact(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'title' => $lead->title,
            'linkedin_url' => $lead->linkedin_url,
            'status' => $lead->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function company(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'domain' => $company->domain,
            'website' => $company->website,
            'location' => $company->location,
            'status' => $company->status->value,
        ];
    }
}
