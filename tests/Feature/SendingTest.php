<?php

use App\Actions\DispatchDueSends;
use App\Actions\EnrolCampaign;
use App\Actions\PersonalizeMessage;
use App\Actions\SendNextStep;
use App\Enums\CampaignLeadStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignStepType;
use App\Enums\EmailAccountStatus;
use App\Enums\EmailStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\OutreachStatus;
use App\Enums\SuppressionLayer;
use App\Jobs\SendCampaignStep;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\EmailAccount;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Suppression;
use App\Models\User;
use App\Services\Outreach\MailboxTester;
use App\Services\Outreach\Sender;
use App\Services\Outreach\SendFailure;
use App\Services\Outreach\SuppressionList;
use App\Support\CurrentProject;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{0: User, 1: Project, 2: EmailAccount}
 */
function sender(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);

    $project = Project::factory()->for($organization)->create();

    $mailbox = EmailAccount::factory()->for($organization)->create(['daily_limit' => 30]);
    $mailbox->projects()->attach($project);

    app(CurrentProject::class)->set($project);

    return [$user, $project, $mailbox];
}

function sequence(Project $project, int $waitHours = 72): Campaign
{
    $campaign = Campaign::factory()->create([
        'project_id' => $project->id,
        'status' => CampaignStatus::Draft,
    ]);

    $first = $campaign->steps()->create([
        'position' => 1,
        'type' => CampaignStepType::Email,
        'config' => ['intent' => 'Open on their ordering.'],
    ]);

    $first->variants()->create(['subject' => 'vos commandes', 'body' => "Bonjour,\n\nRépondez STOP si ce n'est pas pertinent."]);

    $campaign->steps()->create([
        'position' => 2,
        'type' => CampaignStepType::Wait,
        'delay_hours' => $waitHours,
        'config' => ['intent' => 'Let it breathe.'],
    ]);

    $second = $campaign->steps()->create([
        'position' => 3,
        'type' => CampaignStepType::Email,
        'config' => ['intent' => 'One follow-up.'],
    ]);

    $second->variants()->create(['subject' => 'petite relance', 'body' => 'Je reviens une dernière fois.']);

    return $campaign->refresh();
}

function contactable(Project $project, string $email = 'marcel@friterie.test'): Lead
{
    return Lead::factory()->create([
        'project_id' => $project->id,
        'email' => $email,
        'email_status' => EmailStatus::Valid,
        'status' => OutreachStatus::New,
    ]);
}

/**
 * A sender that records what it was asked to send instead of opening a socket.
 * Sending for real in a test is the one thing that must never happen here.
 */
function fakeSender(): object
{
    $fake = new class extends Sender
    {
        /** @var array<int, array{subject: string, body: string, in_reply_to: string|null, to: string}> */
        public array $sent = [];

        public ?string $failWith = null;

        public function send(EmailAccount $account, Lead $lead, string $subject, string $body, ?string $inReplyTo = null): string
        {
            if ($this->failWith !== null) {
                throw SendFailure::fromTransportError($this->failWith);
            }

            $this->sent[] = [
                'to' => (string) $lead->email,
                'subject' => $subject,
                'body' => $body,
                'in_reply_to' => $inReplyTo,
            ];

            return '<'.count($this->sent).'@friterie.test>';
        }
    };

    app()->instance(Sender::class, $fake);

    return $fake;
}

/**
 * Personalisation is a model call, and the point of these tests is the sending
 * around it — so it answers immediately with what it was handed.
 */
beforeEach(function () {
    $this->mock(PersonalizeMessage::class)
        ->shouldReceive('handle')
        ->andReturnUsing(function ($step, $lead) {
            $variant = $step->variants()->first();

            return ['subject' => $variant->subject, 'body' => $variant->body];
        })
        ->byDefault();
});

it('enrols only the people who may actually be written to', function () {
    [, $project, $mailbox] = sender();

    $wanted = contactable($project);
    $client = contactable($project, 'patron@client.test');
    $client->update(['status' => OutreachStatus::Client]);
    $noAddress = Lead::factory()->create(['project_id' => $project->id, 'email' => null]);
    $invalid = contactable($project, 'mort@friterie.test');
    $invalid->update(['email_status' => EmailStatus::Invalid]);

    $optedOut = contactable($project, 'stop@friterie.test');
    Suppression::query()->create([
        'layer' => SuppressionLayer::OptOut,
        'project_id' => $project->id,
        'email' => 'stop@friterie.test',
        'reason' => 'replied STOP',
    ]);

    $campaign = sequence($project);

    expect(app(EnrolCampaign::class)->handle($campaign))->toBe(1);

    $enrolled = CampaignLead::query()->pluck('lead_id');

    expect($enrolled->all())->toBe([$wanted->id])
        ->and($enrolled)->not->toContain($client->id, $noAddress->id, $invalid->id, $optedOut->id)
        // Pinned at enrolment: a follow-up from another address is a different
        // conversation as far as threading goes.
        ->and(CampaignLead::query()->first()->email_account_id)->toBe($mailbox->id);
});

it('cannot enrol anybody when the project has no mailbox attached', function () {
    [, $project, $mailbox] = sender();

    $mailbox->projects()->detach();
    contactable($project);

    // The safe failure: no address attached means nothing can leave, and the
    // campaign waits rather than inventing a sender.
    expect(app(EnrolCampaign::class)->handle(sequence($project)))->toBe(0);
});

it('sends the first mail, records it, and marks the person contacted', function () {
    [, $project] = sender();

    $fake = fakeSender();
    $lead = contactable($project);
    $campaign = sequence($project);

    app(EnrolCampaign::class)->handle($campaign);

    app(SendNextStep::class)->handle(CampaignLead::query()->firstOrFail());

    expect($fake->sent)->toHaveCount(1)
        ->and($fake->sent[0]['to'])->toBe('marcel@friterie.test')
        ->and($fake->sent[0]['subject'])->toBe('vos commandes')
        // The only opt-out channel there is, so it travels in the body.
        ->and($fake->sent[0]['body'])->toContain('STOP')
        ->and($fake->sent[0]['in_reply_to'])->toBeNull();

    $message = Message::query()->sole();

    expect($message->direction)->toBe(MessageDirection::Outbound)
        ->and($message->status)->toBe(MessageStatus::Sent)
        ->and($message->sent_at)->not->toBeNull()
        ->and($lead->refresh()->status)->toBe(OutreachStatus::Contacted)
        ->and($lead->last_contacted_at)->not->toBeNull();
});

it('waits the step it was told to wait, then follows up in the same thread', function () {
    [, $project] = sender();

    $fake = fakeSender();
    contactable($project);
    $campaign = sequence($project, waitHours: 72);

    app(EnrolCampaign::class)->handle($campaign);

    $send = app(SendNextStep::class);
    $membership = CampaignLead::query()->firstOrFail();

    $send->handle($membership);

    // The wait step is not sent, it is waited — and the delay is the step's own.
    $send->handle($membership->refresh());

    expect($fake->sent)->toHaveCount(1)
        ->and($membership->refresh()->current_step_position)->toBe(2)
        ->and($membership->next_action_at->diffInHours(now()->addHours(72), absolute: true))->toBeLessThan(1);

    // Once it is due, the follow-up answers our own first mail rather than
    // arriving as a second cold approach.
    $membership->update(['next_action_at' => now()->subMinute()]);

    $send->handle($membership->refresh());

    expect($fake->sent)->toHaveCount(2)
        ->and($fake->sent[1]['subject'])->toBe('petite relance')
        ->and($fake->sent[1]['in_reply_to'])->toBe('<1@friterie.test>');

    $send->handle($membership->refresh());

    expect($fake->sent)->toHaveCount(2)
        ->and($membership->refresh()->status)->toBe(CampaignLeadStatus::Completed);
});

it('refuses to send to somebody suppressed after they were enrolled', function () {
    [, $project, $mailbox] = sender();

    $fake = fakeSender();
    $lead = contactable($project);
    $campaign = sequence($project);

    app(EnrolCampaign::class)->handle($campaign);

    // The case the whole design is about: a STOP arrives between enrolment and
    // the send, so a list checked "before the campaign" is not a list.
    Suppression::query()->create([
        'layer' => SuppressionLayer::OptOut,
        'project_id' => $project->id,
        'email' => $lead->email,
        'reason' => 'replied STOP',
    ]);

    app(SendNextStep::class)->handle(CampaignLead::query()->firstOrFail());

    expect($fake->sent)->toBeEmpty()
        ->and(CampaignLead::query()->firstOrFail()->status)->toBe(CampaignLeadStatus::Stopped)
        ->and(Message::query()->count())->toBe(0);

    // And a bounce recorded against another mailbox does not stop this one:
    // an address can bounce from one sender and deliver from another.
    expect(app(SuppressionList::class)->suppresses(
        contactable($project, 'autre@friterie.test'),
        $mailbox,
    ))->toBeFalse();
});

it('suppresses an address the server called dead, and never retries it', function () {
    [, $project, $mailbox] = sender();

    $fake = fakeSender();
    $fake->failWith = '550 5.1.1 <marcel@friterie.test>: Recipient address rejected: User unknown';

    $lead = contactable($project);
    app(EnrolCampaign::class)->handle(sequence($project));

    app(SendNextStep::class)->handle(CampaignLead::query()->firstOrFail());

    expect(Suppression::query()->where('layer', SuppressionLayer::Bounce)->where('email_account_id', $mailbox->id)->count())->toBe(1)
        ->and($lead->refresh()->status)->toBe(OutreachStatus::Suppressed)
        ->and(Message::query()->sole()->status)->toBe(MessageStatus::Bounced)
        ->and(CampaignLead::query()->firstOrFail()->status)->toBe(CampaignLeadStatus::Stopped)
        ->and($mailbox->refresh()->status)->toBe(EmailAccountStatus::Active);
});

it('pauses the mailbox rather than the address when the login is refused', function () {
    [, $project, $mailbox] = sender();

    $fake = fakeSender();
    $fake->failWith = '535 5.7.8 Username and password not accepted';

    $lead = contactable($project);
    app(EnrolCampaign::class)->handle(sequence($project));

    app(SendNextStep::class)->handle(CampaignLead::query()->firstOrFail());

    // The mailbox is broken, not the person: suppressing the address here would
    // throw away a good lead over somebody's expired password.
    expect($mailbox->refresh()->status)->toBe(EmailAccountStatus::Error)
        ->and($mailbox->last_error)->toContain('535')
        ->and($lead->refresh()->status)->toBe(OutreachStatus::New)
        ->and(Suppression::query()->count())->toBe(0)
        ->and(CampaignLead::query()->firstOrFail()->status)->toBe(CampaignLeadStatus::Paused);
});

it('retries later on a transient refusal without deciding anything', function () {
    [, $project, $mailbox] = sender();

    $fake = fakeSender();
    $fake->failWith = '451 4.7.1 Greylisted, try again later';

    $lead = contactable($project);
    app(EnrolCampaign::class)->handle(sequence($project));

    app(SendNextStep::class)->handle(CampaignLead::query()->firstOrFail());

    expect($lead->refresh()->status)->toBe(OutreachStatus::New)
        ->and(Suppression::query()->count())->toBe(0)
        ->and($mailbox->refresh()->status)->toBe(EmailAccountStatus::Active)
        ->and(CampaignLead::query()->firstOrFail()->next_action_at->isFuture())->toBeTrue();
});

it('stops at the daily allowance, counted across every project sharing the mailbox', function () {
    [, $project, $mailbox] = sender();

    $mailbox->update(['daily_limit' => 2]);

    // A second project on the same address. The quota belongs to the mailbox,
    // because one quota is what the receiving server counts — count per
    // campaign and an address rated for two sends four.
    $other = Project::factory()->for($mailbox->organization)->create();
    $mailbox->projects()->attach($other);

    foreach ([$project, $other] as $owner) {
        Message::factory()->create([
            'email_account_id' => $mailbox->id,
            'lead_id' => Lead::factory()->create(['project_id' => $owner->id])->id,
            'direction' => MessageDirection::Outbound,
            'status' => MessageStatus::Sent,
            'sent_at' => now()->subMinutes(30),
        ]);
    }

    expect($mailbox->remainingToday())->toBe(0);

    Queue::fake();
    contactable($project);
    app(EnrolCampaign::class)->handle(sequence($project));

    $this->travelTo(now()->setTime(10, 0));

    expect(app(DispatchDueSends::class)->handle())->toBe(0);
    Queue::assertNothingPushed();
});

it('queues one mail per mailbox per tick, and nothing outside the sending window', function () {
    [, $project, $mailbox] = sender();

    Queue::fake();

    contactable($project);
    contactable($project, 'second@friterie.test');
    app(EnrolCampaign::class)->handle(sequence($project));

    // Before dawn nothing leaves, however overdue it is: a 03:00 mail from
    // somebody's own mailbox reads as a machine before it reads as anything.
    $this->travelTo(now()->setTime(3, 0));
    CampaignLead::query()->update(['next_action_at' => now()->subMinutes(10)]);

    expect(app(DispatchDueSends::class)->handle())->toBe(0);

    // Everything is due at once, which is exactly the burst pacing prevents.
    $this->travelTo(now()->setTime(10, 0));

    expect(app(DispatchDueSends::class)->handle())->toBe(1);

    Queue::assertPushed(SendCampaignStep::class, 1);
    Queue::assertPushed(fn (SendCampaignStep $job): bool => $job->queue === 'sending');

    // Still one: the gap between two mails from one address is the point.
    Message::factory()->create([
        'email_account_id' => $mailbox->id,
        'lead_id' => Lead::query()->first()->id,
        'direction' => MessageDirection::Outbound,
        'status' => MessageStatus::Sent,
        'sent_at' => now(),
    ]);

    expect(app(DispatchDueSends::class)->handle())->toBe(0);
});

it('pauses a mailbox whose recent sends are bouncing, whatever the autonomy level', function () {
    [, $project, $mailbox] = sender();

    Queue::fake();
    contactable($project);
    app(EnrolCampaign::class)->handle(sequence($project));
    CampaignLead::query()->update(['next_action_at' => now()->subMinute()]);

    foreach (range(1, 10) as $i) {
        Message::factory()->create([
            'email_account_id' => $mailbox->id,
            'lead_id' => Lead::query()->first()->id,
            'direction' => MessageDirection::Outbound,
            'status' => $i <= 2 ? MessageStatus::Bounced : MessageStatus::Sent,
            'sent_at' => now()->subHours(2),
        ]);
    }

    $this->travelTo(now()->setTime(10, 0));

    expect(app(DispatchDueSends::class)->handle())->toBe(0)
        ->and($mailbox->refresh()->status)->toBe(EmailAccountStatus::Paused)
        ->and($mailbox->last_error)->toContain('bounced');

    Queue::assertNothingPushed();
});

it('ramps a new mailbox up instead of opening at the full limit', function () {
    [, , $mailbox] = sender();

    $mailbox->update(['daily_limit' => 30, 'ramp_up_started_at' => now()]);

    expect($mailbox->allowanceForToday())->toBe(5);

    $mailbox->update(['ramp_up_started_at' => now()->subDays(3)]);

    expect($mailbox->refresh()->allowanceForToday())->toBe(20);

    $mailbox->update(['ramp_up_started_at' => now()->subDays(30)]);

    expect($mailbox->refresh()->allowanceForToday())->toBe(30);
});

it('saves a mailbox, grants it to chosen projects only, and never returns the passwords', function () {
    [$user, $project] = sender();

    $mine = Project::factory()->for($user->organizations()->sole())->create(['name' => 'Second product']);
    $someoneElses = Project::factory()->create();

    $this->actingAs($user)
        ->post(route('settings.mailboxes.store'), [
            'name' => 'Contact',
            'from_name' => 'Clement',
            'from_email' => 'clement@abcreche.test',
            'smtp_host' => 'smtp.infomaniak.com',
            'smtp_port' => 587,
            'smtp_username' => 'clement@abcreche.test',
            'smtp_password' => 'app-password',
            'smtp_encryption' => 'starttls',
            'imap_host' => 'imap.infomaniak.com',
            'imap_port' => 993,
            'imap_username' => 'clement@abcreche.test',
            'imap_password' => 'app-password',
            'imap_encryption' => 'tls',
            'daily_limit' => 25,
            'projects' => [$project->id, $mine->id],
        ])
        ->assertRedirect(route('settings.mailboxes.index'));

    $mailbox = EmailAccount::query()->where('from_email', 'clement@abcreche.test')->sole();

    expect($mailbox->projects->pluck('id')->sort()->values()->all())->toBe([$project->id, $mine->id])
        ->and($mailbox->smtp_password)->toBe('app-password')
        // Encrypted at rest with CREDENTIALS_KEY, never readable as stored.
        ->and($mailbox->getRawOriginal('smtp_password'))->not->toBe('app-password');

    $this->actingAs($user)->get(route('settings.mailboxes.index'))
        ->assertInertia(fn ($page) => $page
            ->has('mailboxes', 2)
            ->missing('mailboxes.0.smtp_password')
            ->missing('mailboxes.0.imap_password'));
});

it('keeps the stored password when an edit leaves the field blank', function () {
    [$user, , $mailbox] = sender();

    $this->actingAs($user)
        ->put(route('settings.mailboxes.update', $mailbox), [
            'name' => 'Renamed',
            'from_name' => $mailbox->from_name,
            'from_email' => $mailbox->from_email,
            'smtp_host' => $mailbox->smtp_host,
            'smtp_port' => $mailbox->smtp_port,
            'smtp_username' => $mailbox->smtp_username,
            'smtp_password' => '',
            'imap_host' => $mailbox->imap_host,
            'imap_port' => $mailbox->imap_port,
            'imap_username' => $mailbox->imap_username,
            'imap_password' => '',
            'daily_limit' => 30,
        ])
        ->assertRedirect();

    // The screen never receives the password, so a blank field means "the one
    // you already have" — treating it as an empty value would break sending on
    // every unrelated edit.
    expect($mailbox->refresh()->name)->toBe('Renamed')
        ->and($mailbox->smtp_password)->toBe('secret');
});

it('never touches a mailbox belonging to another organization', function () {
    [$user] = sender();

    $theirs = EmailAccount::factory()->create();

    $this->actingAs($user)->delete(route('settings.mailboxes.destroy', $theirs))->assertNotFound();
    $this->actingAs($user)->post(route('settings.mailboxes.test', $theirs))->assertNotFound();

    expect($theirs->exists())->toBeTrue();
});

it('names the cause when a mailbox refuses the login, instead of saying it failed', function () {
    $tester = app(MailboxTester::class);
    $explain = new ReflectionMethod($tester, 'explain');

    // The whole point of the story: three of these are the same 535 to the
    // server and three completely different fixes to the user, one of which
    // they cannot perform themselves at all.
    expect($explain->invoke($tester, '535 Please log in with your web browser: https://accounts.google.com/signin/continue', 'smtp.gmail.com', 587))
        ->toContain('app password')
        ->and($explain->invoke($tester, '535 5.7.139 Authentication unsuccessful, SmtpClientAuthentication is disabled for the Tenant', 'smtp.office365.com', 587))
        ->toContain('SMTP AUTH is off')
        ->and($explain->invoke($tester, 'Connection could not be established with host smtp.ovh.net: stream_socket_client(): Connection timed out', 'smtp.ovh.net', 587))
        ->toContain('blocked')
        ->and($explain->invoke($tester, 'SSL routines: wrong version number', 'imap.gandi.net', 143))
        ->toContain('465 and 993 are implicit TLS')
        ->and($explain->invoke($tester, 'php_network_getaddresses: getaddrinfo failed: Name or service not known', 'smpt.typo.test', 587))
        ->toContain('does not resolve')
        // Anything unrecognised keeps the server's own words: a sentence we
        // invented would be less useful than the raw truth.
        ->and($explain->invoke($tester, '452 Too many recipients', 'mail.test', 25))
        ->toContain('452 Too many recipients');
});

it('records what a connection test found, and clears the error when it works', function () {
    [$user, , $mailbox] = sender();

    $mailbox->update(['status' => EmailAccountStatus::Error, 'last_error' => 'SMTP: something old']);

    $this->mock(MailboxTester::class)->shouldReceive('test')->once()->andReturn(null);

    $this->actingAs($user)->post(route('settings.mailboxes.test', $mailbox))->assertRedirect();

    // A mailbox stopped by an expired password works again the moment the new
    // one answers; nobody should have to guess that.
    expect($mailbox->refresh()->status)->toBe(EmailAccountStatus::Active)
        ->and($mailbox->last_error)->toBeNull()
        ->and($mailbox->last_checked_at)->not->toBeNull();

    $this->mock(MailboxTester::class)->shouldReceive('test')->once()->andReturn('SMTP: the server rejected this username and password.');

    $this->actingAs($user)->post(route('settings.mailboxes.test', $mailbox))
        ->assertSessionHasErrors('test');

    expect($mailbox->refresh()->status)->toBe(EmailAccountStatus::Error)
        ->and($mailbox->last_error)->toContain('rejected');
});

it('puts people into the sequence when the campaign is activated, and only then', function () {
    [$user, $project] = sender();

    contactable($project);
    $campaign = sequence($project);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->put(route('campaigns.update', $campaign), ['name' => $campaign->name, 'status' => 'draft'])
        ->assertRedirect();

    expect(CampaignLead::query()->count())->toBe(0);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->put(route('campaigns.update', $campaign), ['name' => $campaign->name, 'status' => 'active'])
        ->assertRedirect();

    expect(CampaignLead::query()->count())->toBe(1);

    // Saving an already-live campaign must not re-add anybody: somebody
    // suppressed or won since activation would walk straight back in.
    CampaignLead::query()->update(['status' => CampaignLeadStatus::Stopped, 'pause_reason' => 'suppressed']);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->put(route('campaigns.update', $campaign), ['name' => 'Renamed', 'status' => 'active'])
        ->assertRedirect();

    expect(CampaignLead::query()->count())->toBe(1);
});

it('refuses to grant a mailbox to a project another organization owns', function () {
    [$user, $project] = sender();

    // The ids come from a form, so ownership is a validation rule and not a
    // filter applied afterwards: a foreign id is tampering, and a silent drop
    // would look exactly like the grant having worked.
    $this->actingAs($user)
        ->post(route('settings.mailboxes.store'), [
            'name' => 'Contact',
            'from_name' => 'Clement',
            'from_email' => 'clement@abcreche.test',
            'smtp_host' => 'smtp.infomaniak.com',
            'smtp_port' => 587,
            'smtp_username' => 'clement@abcreche.test',
            'smtp_password' => 'app-password',
            'imap_host' => 'imap.infomaniak.com',
            'imap_port' => 993,
            'imap_username' => 'clement@abcreche.test',
            'imap_password' => 'app-password',
            'daily_limit' => 25,
            'projects' => [$project->id, Project::factory()->create()->id],
        ])
        ->assertSessionHasErrors('projects.1');

    expect(EmailAccount::query()->where('from_email', 'clement@abcreche.test')->exists())->toBeFalse();
});
