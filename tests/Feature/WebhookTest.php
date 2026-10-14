<?php

use App\Actions\SetOutreachStatus;
use App\Enums\MessageDirection;
use App\Enums\OutreachStatus;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Project;
use App\Models\Webhook;
use App\Services\Outreach\ReplyOutcomes;
use App\Services\Webhooks\WebhookSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function webhookLead(Project $project): Lead
{
    $company = Company::factory()->for($project)->create(['status' => OutreachStatus::Contacted]);

    return Lead::factory()->for($project)->create(['company_id' => $company->id, 'status' => OutreachStatus::Contacted]);
}

function inboundReply(Lead $lead): Message
{
    return Message::factory()->create(['lead_id' => $lead->id, 'direction' => MessageDirection::Inbound]);
}

it('tells a subscribed endpoint about a classified reply, once', function () {
    Queue::fake();
    [, $project] = sender();
    $webhook = Webhook::factory()->for($project)->create();
    $reply = inboundReply(webhookLead($project));

    app(ReplyOutcomes::class)->needsHuman($reply, interested: true);
    app(ReplyOutcomes::class)->notInterested($reply->fresh());

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->webhook->is($webhook)
        && $job->payload['event'] === 'reply.received'
        && $job->payload['reply']['classification'] === 'interested'
        && $job->payload['contact']['email'] === $reply->lead->email);
    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->payload['event'] === 'reply.received', 1);
});

it('never sends an out-of-office', function () {
    Queue::fake();
    [, $project] = sender();
    Webhook::factory()->for($project)->create(['events' => [WebhookEvent::ReplyReceived->value]]);

    app(ReplyOutcomes::class)->ignore(inboundReply(webhookLead($project)));

    Queue::assertNothingPushed();
});

it('sends a status change to its own project only, and only when it changed', function () {
    Queue::fake();
    [, $project] = sender();
    $other = Project::factory()->for($project->organization)->create();
    Webhook::factory()->for($project)->create(['events' => [WebhookEvent::StatusChanged->value]]);
    Webhook::factory()->for($other)->create();
    $lead = webhookLead($project);

    app(SetOutreachStatus::class)->forLead($lead, OutreachStatus::Won);
    app(SetOutreachStatus::class)->forLead($lead->fresh(), OutreachStatus::Won);

    Queue::assertPushed(DeliverWebhook::class, 1);
    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->webhook->project_id === $project->id
        && $job->payload['status'] === 'won'
        && $job->payload['previous_status'] === 'contacted'
        && $job->payload['company']['id'] === $lead->company_id);
});

it('signs the body and records a failing endpoint, so the job retries', function () {
    [, $project] = sender();
    $webhook = Webhook::factory()->for($project)->create(['secret' => 'whsec_test']);
    Http::fake(['hooks.example.com/*' => Http::sequence()->push('', 200)->push('', 500)]);

    expect(app(WebhookSender::class)->send($webhook, ['id' => 'a', 'event' => 'ping', 'body' => "é/\u{2028}"]))->toBeNull();

    // Byte for byte what JSON.stringify writes, or the n8n recipe, which can
    // only re-serialise the parsed body, rejects a genuine request.
    Http::assertSent(fn (Request $request): bool => $request->header('X-Eveil-Signature')[0] === 'sha256='.hash_hmac('sha256', $request->body(), 'whsec_test')
        && $request->body() === '{"id":"a","event":"ping","body":"é/'."\u{2028}".'"}');

    expect(fn () => (new DeliverWebhook($webhook, ['id' => 'b', 'event' => 'ping']))->handle(app(WebhookSender::class)))
        ->toThrow(RuntimeException::class);
    expect($webhook->fresh())->last_status->toBe(500)->last_error->toBe('HTTP 500');
});

it('refuses a private address on cloud, and allows it self-hosted', function () {
    [$user] = sender();
    $this->actingAs($user);

    config()->set('eveil.edition', 'cloud');
    $this->post(route('settings.webhooks.store'), ['url' => 'http://169.254.169.254/latest', 'events' => ['reply.received']])
        ->assertSessionHasErrors('url');

    config()->set('eveil.edition', 'self');
    $this->post(route('settings.webhooks.store'), ['url' => 'http://n8n:5678/webhook/eveil', 'events' => ['reply.received']])
        ->assertSessionHas('webhookSecret');

    expect(Webhook::query()->sole()->secret)->toStartWith('whsec_');
});

it('never lets one project delete or test another project webhook', function () {
    [$user] = sender();
    $theirs = Webhook::factory()->create();
    $this->actingAs($user);

    $this->delete(route('settings.webhooks.destroy', $theirs->id))->assertNotFound();
    $this->post(route('settings.webhooks.test', $theirs->id))->assertNotFound();

    expect(Webhook::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('turns an endpoint off after a day of failures, and a successful test turns it back on', function () {
    Queue::fake();
    [$user, $project] = sender();
    $webhook = Webhook::factory()->for($project)->create();
    Http::fake(['hooks.example.com/*' => Http::sequence()->push('', 500)->push('', 500)->push('', 200)]);

    app(WebhookSender::class)->send($webhook, ['id' => 'a', 'event' => 'ping']);
    expect($webhook->fresh()->disabled_at)->toBeNull();

    $this->travel(25)->hours();
    app(WebhookSender::class)->send($webhook->fresh(), ['id' => 'b', 'event' => 'ping']);
    expect($webhook->fresh()->disabled_at)->not->toBeNull();

    app(SetOutreachStatus::class)->forLead(webhookLead($project), OutreachStatus::Won);
    Queue::assertNothingPushed();

    $this->actingAs($user)->post(route('settings.webhooks.test', $webhook->id))->assertSessionHasNoErrors();
    expect($webhook->fresh())->disabled_at->toBeNull()->failing_since->toBeNull();
});
