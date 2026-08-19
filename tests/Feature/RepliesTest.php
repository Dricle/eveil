<?php

use App\Actions\FetchReplies;
use App\Ai\Agents\ReplyHandler;
use App\Ai\Tools\IgnoreReply;
use App\Ai\Tools\MarkNeedsHuman;
use App\Ai\Tools\RescheduleFollowUp;
use App\Ai\Tools\SuppressLead;
use App\Enums\CampaignLeadStatus;
use App\Enums\EmailAccountStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\OutreachStatus;
use App\Enums\ReplyClassification;
use App\Enums\SuppressionLayer;
use App\Jobs\HandleReply;
use App\Models\CampaignLead;
use App\Models\EmailAccount;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Project;
use App\Models\Suppression;
use App\Models\User;
use App\Services\Outreach\ImapClient;
use App\Services\Outreach\ImapFailure;
use App\Services\Outreach\InboundMail;
use App\Services\Outreach\MailParser;
use App\Services\Outreach\OptOutPhrases;
use App\Services\Outreach\ReplyOutcomes;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request as ToolRequest;

/**
 * A mailbox with one mail already sent to one lead — the state every reply
 * arrives into.
 *
 * @return array{0: EmailAccount, 1: CampaignLead, 2: Message}
 */
function awaitingReply(): array
{
    [, $project, $mailbox] = sender();

    $lead = contactable($project, 'marcel@friterie.test');
    $campaign = sequence($project);

    $membership = CampaignLead::query()->create([
        'campaign_id' => $campaign->id,
        'lead_id' => $lead->id,
        'email_account_id' => $mailbox->id,
        'current_step_position' => 1,
        'status' => CampaignLeadStatus::Running,
        'next_action_at' => now()->addHours(72),
    ]);

    $sent = Message::query()->create([
        'lead_id' => $lead->id,
        'campaign_lead_id' => $membership->id,
        'email_account_id' => $mailbox->id,
        'direction' => MessageDirection::Outbound,
        'message_id' => 'ours-1@abcreche.test',
        'subject' => 'vos commandes',
        'body' => "Bonjour,\n\nRépondez STOP si ce n'est pas pertinent.",
        'status' => MessageStatus::Sent,
        'sent_at' => now()->subDay(),
    ]);

    return [$mailbox, $membership, $sent];
}

/**
 * An IMAP that hands back the mails a test names, instead of opening a socket.
 */
function fakeImap(array $mails): void
{
    app()->instance(ImapClient::class, new class($mails) extends ImapClient
    {
        public function __construct(private array $mails) {}

        public function fetchSince(EmailAccount $account, ?int $lastUid): array
        {
            return $this->mails;
        }
    });
}

function inbound(string $body, array $overrides = []): InboundMail
{
    return new InboundMail(
        uid: $overrides['uid'] ?? 12,
        messageId: $overrides['messageId'] ?? 'theirs-1@friterie.test',
        // `array_key_exists`, not `??`: a mail that answers nothing passes an
        // explicit null, which `??` would replace with the default.
        inReplyTo: array_key_exists('inReplyTo', $overrides) ? $overrides['inReplyTo'] : 'ours-1@abcreche.test',
        from: $overrides['from'] ?? 'marcel@friterie.test',
        subject: $overrides['subject'] ?? 'Re: vos commandes',
        body: $body,
        isAutoReply: $overrides['isAutoReply'] ?? false,
    );
}

it('attributes a reply by header, records it, and pauses before deciding anything', function () {
    Queue::fake();

    [$mailbox, $membership] = awaitingReply();

    fakeImap([inbound('Bonjour, oui ça m\'intéresse. Quels sont vos tarifs ?')]);

    expect(app(FetchReplies::class)->handle($mailbox))->toBe(1);

    $reply = Message::query()->where('direction', MessageDirection::Inbound)->sole();

    expect($reply->campaign_lead_id)->toBe($membership->id)
        ->and($reply->in_reply_to)->toBe('ours-1@abcreche.test')
        ->and($reply->received_at)->not->toBeNull()
        // The pause is deterministic and happens BEFORE the agent: the next
        // follow-up must not leave while we work out what the answer meant.
        ->and($membership->refresh()->status)->toBe(CampaignLeadStatus::Paused)
        ->and($membership->pause_reason)->toBe('replied')
        ->and($membership->lead->refresh()->status)->toBe(OutreachStatus::Replied)
        ->and($mailbox->refresh()->last_inbound_uid)->toBe(12);

    Queue::assertPushed(HandleReply::class, 1);
    Queue::assertPushed(fn (HandleReply $job): bool => $job->queue === 'ai');
});

it('leaves alone anything that does not answer one of our own mails', function () {
    Queue::fake();

    [$mailbox] = awaitingReply();

    fakeImap([
        // A newsletter, an invoice, a colleague: the mailbox is the user's own.
        inbound('Votre facture est disponible.', ['inReplyTo' => null, 'messageId' => 'invoice@ovh.test']),
        inbound('Re: something else', ['inReplyTo' => 'not-ours@elsewhere.test', 'messageId' => 'x@elsewhere.test', 'uid' => 13]),
    ]);

    expect(app(FetchReplies::class)->handle($mailbox))->toBe(0)
        ->and(Message::query()->where('direction', MessageDirection::Inbound)->count())->toBe(0)
        // The UID still advances, or every poll would re-read them for ever.
        ->and($mailbox->refresh()->last_inbound_uid)->toBe(13);

    Queue::assertNothingPushed();
});

it('never pauses on an out-of-office, and never pays a model call for one', function () {
    Queue::fake();

    [$mailbox, $membership] = awaitingReply();

    fakeImap([inbound('Je suis absent jusqu\'au 30 août.', ['isAutoReply' => true])]);

    app(FetchReplies::class)->handle($mailbox);

    // A fortnight's holiday is not a reply. The headers say so, so the agent
    // is never asked and the sequence never stops.
    expect($membership->refresh()->status)->toBe(CampaignLeadStatus::Running)
        ->and(Message::query()->where('direction', MessageDirection::Inbound)->sole()->classification)
        ->toBe(ReplyClassification::AutoReply);

    Queue::assertNothingPushed();
});

it('suppresses an unmistakable opt-out without waiting for the agent', function () {
    Queue::fake();

    [$mailbox, $membership] = awaitingReply();

    fakeImap([inbound('Désinscrivez-moi de votre liste, merci.')]);

    app(FetchReplies::class)->handle($mailbox);

    // The net under the agent: compliance cannot depend on a provider being up.
    expect(Suppression::query()->where('layer', SuppressionLayer::OptOut)->count())->toBe(1)
        ->and($membership->lead->refresh()->status)->toBe(OutreachStatus::Suppressed)
        ->and($membership->refresh()->status)->toBe(CampaignLeadStatus::Stopped);

    // And the agent still runs: "stop, and send it to my colleague instead"
    // needs both halves.
    Queue::assertPushed(HandleReply::class, 1);
});

it('records the same reply once, however many times it is fetched', function () {
    Queue::fake();

    [$mailbox] = awaitingReply();

    fakeImap([inbound('Merci, je regarde ça.')]);

    app(FetchReplies::class)->handle($mailbox);
    app(FetchReplies::class)->handle($mailbox);

    expect(Message::query()->where('direction', MessageDirection::Inbound)->count())->toBe(1);
});

it('puts the mailbox in error when its inbox cannot be read', function () {
    [$mailbox] = awaitingReply();

    app()->instance(ImapClient::class, new class extends ImapClient
    {
        public function fetchSince(EmailAccount $account, ?int $lastUid): array
        {
            throw new ImapFailure('a1 NO [AUTHENTICATIONFAILED] Invalid credentials');
        }
    });

    expect(app(FetchReplies::class)->handle($mailbox))->toBe(0);

    // The same two fields the send half and the connection test use: one broken
    // mailbox is one problem to the user, not two.
    expect($mailbox->refresh()->status)->toBe(EmailAccountStatus::Error)
        ->and($mailbox->last_error)->toContain('IMAP')
        ->and($mailbox->last_error)->toContain('AUTHENTICATIONFAILED');
});

/**
 * The reply row a tool acts on, with its sequence already paused — which is the
 * state every tool is called in, because the pause happens before the agent.
 */
function pausedReply(string $body = 'Merci, je regarde.'): Message
{
    Queue::fake();

    [$mailbox] = awaitingReply();

    fakeImap([inbound($body)]);

    app(FetchReplies::class)->handle($mailbox);

    return Message::query()->where('direction', MessageDirection::Inbound)->sole();
}

it('suppresses through the tool, and stops the sequence for good', function () {
    $reply = pausedReply('Please take me off your list.');

    // The deterministic net already fired on that sentence, so the state is
    // asserted after the tool to prove the tool alone is sufficient.
    Suppression::query()->delete();
    $reply->lead->update(['status' => OutreachStatus::Replied]);

    (new SuppressLead($reply))->handle(new ToolRequest(['reason' => 'asked to be removed']));

    expect(Suppression::query()->sole()->layer)->toBe(SuppressionLayer::OptOut)
        ->and(Suppression::query()->sole()->project_id)->toBe($reply->lead->project_id)
        ->and($reply->refresh()->classification)->toBe(ReplyClassification::Unsubscribe)
        ->and($reply->lead->refresh()->status)->toBe(OutreachStatus::Suppressed)
        ->and($reply->campaignLead->refresh()->status)->toBe(CampaignLeadStatus::Stopped)
        ->and($reply->campaignLead->next_action_at)->toBeNull();
});

it('escalates an opt-out to the whole organization on the second project', function () {
    $reply = pausedReply('Stop.');

    $organizationId = $reply->lead->project->organization_id;
    $email = $reply->lead->email;

    // The same person told a second project of this organization the same
    // thing. There is no unsubscribe page to click, so the scope widens by
    // itself rather than waiting for a complaint.
    $second = Project::factory()->create(['organization_id' => $organizationId]);
    Suppression::query()->create([
        'layer' => SuppressionLayer::OptOut,
        'project_id' => $second->id,
        'organization_id' => $organizationId,
        'email' => $email,
        'reason' => 'replied STOP',
    ]);

    app(ReplyOutcomes::class)->suppress($reply);

    expect(Suppression::query()
        ->where('layer', SuppressionLayer::OptOut)
        ->where('organization_id', $organizationId)
        ->whereNull('project_id')
        ->where('email', $email)
        ->exists())->toBeTrue();
});

it('resumes the sequence when the agent recognises a machine', function () {
    $reply = pausedReply('Je suis en congé jusqu\'au 30.');

    expect($reply->campaignLead->refresh()->status)->toBe(CampaignLeadStatus::Paused);

    (new IgnoreReply($reply))->handle(new ToolRequest(['kind' => 'out of office']));

    // The pause is deterministic, so ignoring is what must undo it — otherwise
    // a fortnight's holiday would end the sequence.
    expect($reply->campaignLead->refresh()->status)->toBe(CampaignLeadStatus::Running)
        ->and($reply->campaignLead->paused_at)->toBeNull()
        ->and($reply->refresh()->classification)->toBe(ReplyClassification::AutoReply);
});

it('holds an interested reply for a human and never answers it itself', function () {
    $reply = pausedReply('Oui, ça m\'intéresse. Quels sont vos tarifs ?');

    (new MarkNeedsHuman($reply))->handle(new ToolRequest(['interested' => true, 'summary' => 'Wants pricing.']));

    expect($reply->refresh()->classification)->toBe(ReplyClassification::Interested)
        ->and($reply->classification->isPositive())->toBeTrue()
        ->and($reply->campaignLead->refresh()->status)->toBe(CampaignLeadStatus::Paused)
        ->and($reply->campaignLead->pause_reason)->toBe('awaiting_human')
        // Nothing was written back: the promise is that these read as one
        // person writing to another.
        ->and(Message::query()->where('direction', MessageDirection::Outbound)->count())->toBe(1);
});

it('counts an ambiguous reply as needing a person, not as a positive', function () {
    $reply = pausedReply('Peut-être, dites-moi en plus.');

    (new MarkNeedsHuman($reply))->handle(new ToolRequest(['interested' => false, 'summary' => 'Asked for more.']));

    // The number the whole product is judged on: calling everything that is not
    // a refusal positive is the inflation this metric exists to refuse.
    expect($reply->refresh()->classification)->toBe(ReplyClassification::NeedsHuman)
        ->and($reply->classification->isPositive())->toBeFalse()
        ->and($reply->classification->needsAttention())->toBeTrue();
});

it('puts a postponed lead back in the queue months later instead of dropping it', function () {
    $reply = pausedReply('Rappelez-moi en septembre, budget pas voté.');

    (new RescheduleFollowUp($reply))->handle(new ToolRequest(['months' => 4, 'summary' => 'Budget in September.']));

    $membership = $reply->campaignLead->refresh();

    expect($reply->refresh()->classification)->toBe(ReplyClassification::NotNow)
        ->and($membership->pause_reason)->toBe('not_now')
        ->and($membership->next_action_at->diffInDays(now()->addMonths(4), absolute: true))->toBeLessThan(2);
});

it('gives the agent every decision and no way to write a reply', function () {
    [, $project] = sender();

    $reply = Message::factory()->create([
        'lead_id' => Lead::factory()->create(['project_id' => $project->id])->id,
        'email_account_id' => EmailAccount::factory()->create()->id,
        'direction' => MessageDirection::Inbound,
    ]);

    $tools = collect((new ReplyHandler($project, $reply))->tools())
        ->map(fn (object $tool): string => class_basename($tool))
        ->all();

    expect($tools)->toBe([
        'SuppressLead',
        'MarkNotInterested',
        'MarkNeedsHuman',
        'RescheduleFollowUp',
        'AskForRightContact',
        'IgnoreReply',
    ]);
});

it('reads an opt-out however it is written, and leaves a sales objection alone', function () {
    $phrases = app(OptOutPhrases::class);

    // The mail was written in the prospect's language, so this has to work in
    // theirs. Missing one costs a complaint, not a lead.
    expect($phrases->found('STOP'))->toBeTrue()
        ->and($phrases->found('Désinscrivez-moi de votre liste, merci.'))->toBeTrue()
        ->and($phrases->found('je me desabonne'))->toBeTrue()
        ->and($phrases->found('Please remove me from your list'))->toBeTrue()
        ->and($phrases->found('Gelieve mij uit te schrijven'))->toBeTrue()
        ->and($phrases->found('Bitte keine weiteren E-Mails'))->toBeTrue()
        ->and($phrases->found('ne plus me contacter svp'))->toBeTrue()
        // And the ones that only mean "no", which are the agent's to judge:
        // suppressing these would throw away leads for nothing.
        ->and($phrases->found('We stopped using that supplier last year.'))->toBeFalse()
        ->and($phrases->found('Not interested for now, thanks.'))->toBeFalse()
        ->and($phrases->found('Unstoppable growth this year!'))->toBeFalse();
});

it('parses a real reply out of what a mail server actually sends', function () {
    $raw = "* 12 FETCH (BODY[] {480}\r\n"
        ."From: Marcel Dupont <marcel@friterie.test>\r\n"
        ."To: clement@abcreche.test\r\n"
        ."Subject: =?UTF-8?Q?Re=3A_vos_commandes_=C3=A9t=C3=A9?=\r\n"
        ."In-Reply-To: <ours-1@abcreche.test>\r\n"
        ."References: <older@abcreche.test> <ours-1@abcreche.test>\r\n"
        ."Message-ID: <theirs-1@friterie.test>\r\n"
        ."Content-Type: text/plain; charset=UTF-8\r\n"
        ."Content-Transfer-Encoding: quoted-printable\r\n"
        ."\r\n"
        ."Bonjour, c'est tr=C3=A8s int=C3=A9ressant.\r\n"
        ."\r\n"
        ."Le 18 août 2026, Clement a écrit :\r\n"
        ."> Bonjour, votre carte est sur Facebook\r\n"
        .")\r\n";

    $headers = MailParser::headers($raw);

    expect($headers['message-id'])->toBe('<theirs-1@friterie.test>')
        // Accented subjects arrive encoded; leaving them encoded would put
        // mojibake in front of the agent deciding somebody's opt-out.
        ->and($headers['subject'])->toBe('Re: vos commandes été')
        ->and(MailParser::address($headers['from']))->toBe('marcel@friterie.test')
        ->and(MailParser::firstReference($headers))->toBe('ours-1@abcreche.test')
        // Quoted-printable decoded, and our own mail quoted back is dropped:
        // leaving it doubles the prompt and invites the wrong answer.
        ->and(MailParser::body($raw))->toBe("Bonjour, c'est très intéressant.")
        ->and(MailParser::looksAutomatic($headers))->toBeFalse()
        ->and(MailParser::looksAutomatic(['auto-submitted' => 'auto-replied']))->toBeTrue()
        ->and(MailParser::looksAutomatic(['x-auto-response-suppress' => 'All']))->toBeTrue();
});

it('shows only conversations somebody actually answered, ordered by what needs a person', function () {
    Queue::fake();

    [$mailbox, $membership] = awaitingReply();
    $user = User::query()->firstOrFail();
    $project = $membership->campaign->project;

    // Written to, said nothing: a sequence still running, not an inbox entry.
    $silent = contactable($project, 'muet@friterie.test');
    CampaignLead::query()->create([
        'campaign_id' => $membership->campaign_id,
        'lead_id' => $silent->id,
        'email_account_id' => $mailbox->id,
        'current_step_position' => 1,
        'status' => CampaignLeadStatus::Running,
    ]);

    fakeImap([inbound('Oui, ça m\'intéresse.')]);
    app(FetchReplies::class)->handle($mailbox);

    $reply = Message::query()->where('direction', MessageDirection::Inbound)->sole();
    (new MarkNeedsHuman($reply))->handle(new ToolRequest(['interested' => true, 'summary' => 'Wants pricing.']));

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Paginated, so the rows are under `data` — the envelope carries
            // the meta the pager needs.
            ->has('conversations.data', 1)
            ->where('conversations.data.0.lead.email', 'marcel@friterie.test')
            ->where('conversations.data.0.classification', 'interested')
            ->where('conversations.data.0.needs_attention', true)
            // The thread reads in order, both directions.
            ->has('conversations.data.0.messages', 2)
            ->where('conversations.data.0.messages.0.direction', 'outbound')
            ->where('conversations.data.0.messages.1.direction', 'inbound'));
});

it('never shows another project\'s replies', function () {
    Queue::fake();

    [$mailbox] = awaitingReply();
    $user = User::query()->firstOrFail();

    fakeImap([inbound('Merci.')]);
    app(FetchReplies::class)->handle($mailbox);

    // A second project of the same organization, with its own empty inbox.
    $other = Project::factory()->for($user->organizations()->sole())->create();

    $this->actingAs($user)
        ->withSession(['current_project_id' => $other->id])
        ->get(route('inbox'))
        ->assertInertia(fn ($page) => $page->has('conversations.data', 0));
});

it('answers from the mailbox the sequence pinned, in the same thread, and stops the follow-ups', function () {
    Queue::fake();

    [$mailbox, $membership] = awaitingReply();
    $user = User::query()->firstOrFail();
    $project = $membership->campaign->project;

    $fake = fakeSender();

    fakeImap([inbound('Quels sont vos tarifs ?', ['messageId' => 'theirs-9@friterie.test'])]);
    app(FetchReplies::class)->handle($mailbox);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->post(route('inbox.reply', $membership), ['body' => "Bonjour Marcel,\n\nVoici nos tarifs."])
        ->assertRedirect();

    expect($fake->sent)->toHaveCount(1)
        ->and($fake->sent[0]['to'])->toBe('marcel@friterie.test')
        // Threaded onto their reply, and prefixed once — "Re: Re: Re:" is what
        // a machine looks like.
        ->and($fake->sent[0]['in_reply_to'])->toBe('theirs-9@friterie.test')
        ->and($fake->sent[0]['subject'])->toBe('Re: vos commandes')
        // Somebody being written to by hand must not also receive the queued
        // automated follow-up.
        ->and($membership->refresh()->status)->toBe(CampaignLeadStatus::Stopped)
        ->and($membership->pause_reason)->toBe('answered_by_hand')
        ->and($membership->next_action_at)->toBeNull();
});

it('reports the positive reply rate and the funnel, never a raw rate', function () {
    Queue::fake();

    [$mailbox, $membership] = awaitingReply();
    $user = User::query()->firstOrFail();
    $project = $membership->campaign->project;

    fakeImap([inbound('Pas intéressé, merci.')]);
    app(FetchReplies::class)->handle($mailbox);

    $reply = Message::query()->where('direction', MessageDirection::Inbound)->sole();
    app(ReplyOutcomes::class)->notInterested($reply);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.sent', 1)
            ->where('stats.replies', 1)
            // One reply, and it was a refusal: the raw rate would say 100%.
            ->where('stats.positive', 0)
            ->where('stats.positive_rate', 0)
            ->where('pipeline.stopped', 1));
});
