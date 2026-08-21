<?php

use App\Actions\DispatchDueSends;
use App\Actions\EnrolCampaign;
use App\Actions\PersonalizeMessage;
use App\Actions\SendNextStep;
use App\Enums\CampaignLeadStatus;
use App\Enums\CampaignStatus;
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
use App\Models\Project;
use App\Models\Suppression;
use App\Models\User;
use App\Services\Outreach\MailboxTester;
use App\Services\Outreach\Sender;
use App\Services\Outreach\SuppressionList;
use Illuminate\Support\Facades\Queue;

/**
 * Personalisation is a model call, and the point of these tests is the sending
 * around it, so it answers immediately with what it was handed.
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

    // The wait step is not sent, it is waited, and the delay is the step's own.
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
    // because one quota is what the receiving server counts: count per
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
    // you already have": treating it as an empty value would break sending on
    // every unrelated edit.
    expect($mailbox->refresh()->name)->toBe('Renamed')
        ->and($mailbox->smtp_password)->toBe('secret');
});

it('keeps the stored password when the blank edit arrives as JSON', function () {
    [$user, , $mailbox] = sender();

    // Inertia sends application/json, where the input lives in a different bag
    // than a form post: the same edit that works from a test form once wrote a
    // null password straight into a not-null column.
    $this->actingAs($user)
        ->putJson(route('settings.mailboxes.update', $mailbox), [
            'name' => 'Renamed',
            'from_name' => $mailbox->from_name,
            'from_email' => $mailbox->from_email,
            'smtp_host' => $mailbox->smtp_host,
            'smtp_port' => $mailbox->smtp_port,
            'smtp_username' => $mailbox->smtp_username,
            'smtp_password' => null,
            'imap_host' => $mailbox->imap_host,
            'imap_port' => $mailbox->imap_port,
            'imap_username' => $mailbox->imap_username,
            'imap_password' => null,
            'daily_limit' => 30,
        ])
        ->assertRedirect();

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
    $account = EmailAccount::factory()->make([
        'from_email' => 'clement@example.test',
        'smtp_username' => 'clement@other.test',
    ]);

    // The whole point of the story: three of these are the same 535 to the
    // server and three completely different fixes to the user, one of which
    // they cannot perform themselves at all.
    expect($explain->invoke($tester, '535 Please log in with your web browser: https://accounts.google.com/signin/continue', 'smtp.gmail.com', 587, $account))
        ->toContain('app password')
        ->and($explain->invoke($tester, '535 5.7.139 Authentication unsuccessful, SmtpClientAuthentication is disabled for the Tenant', 'smtp.office365.com', 587, $account))
        ->toContain('SMTP AUTH is off')
        ->and($explain->invoke($tester, 'Connection could not be established with host smtp.ovh.net: stream_socket_client(): Connection timed out', 'smtp.ovh.net', 587, $account))
        ->toContain('blocked')
        ->and($explain->invoke($tester, 'SSL routines: wrong version number', 'imap.gandi.net', 143, $account))
        ->toContain('465 and 993 are implicit TLS')
        ->and($explain->invoke($tester, 'php_network_getaddresses: getaddrinfo failed: Name or service not known', 'smpt.typo.test', 587, $account))
        ->toContain('does not resolve')
        // The login works and the envelope does not: a different mistake with
        // a different fix, and it only shows once MAIL FROM is spoken.
        ->and($explain->invoke($tester, 'Expected response code "250" but got code "553", with message "553 Sender is not allowed to relay emails".', 'smtppro.zoho.eu', 465, $account))
        ->toContain('will not send as clement@example.test')
        ->and($explain->invoke($tester, '553 Sender is not allowed to relay emails', 'smtppro.zoho.eu', 465, $account))
        ->toContain('alias of clement@other.test')
        // Anything unrecognised keeps the server's own words: a sentence we
        // invented would be less useful than the raw truth.
        ->and($explain->invoke($tester, '452 Too many recipients', 'mail.test', 25, $account))
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
        ->put(route('campaigns.status', $campaign), ['status' => 'draft'])
        ->assertRedirect();

    expect(CampaignLead::query()->count())->toBe(0);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->put(route('campaigns.status', $campaign), ['status' => 'active'])
        ->assertRedirect();

    expect(CampaignLead::query()->count())->toBe(1);

    // Switching an already-live campaign must not re-add anybody: somebody
    // suppressed or won since activation would walk straight back in.
    CampaignLead::query()->update(['status' => CampaignLeadStatus::Stopped, 'pause_reason' => 'suppressed']);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->put(route('campaigns.status', $campaign), ['status' => 'active'])
        ->assertRedirect();

    expect(CampaignLead::query()->count())->toBe(1);

    // Renaming is a different edit and must not touch the switch, nor put
    // anybody back into a sequence they were taken out of.
    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->put(route('campaigns.update', $campaign), ['name' => 'Renamed'])
        ->assertRedirect();

    expect($campaign->fresh()->name)->toBe('Renamed')
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Active)
        ->and(CampaignLead::query()->count())->toBe(1);
});

it('carries the empty aggregates on the campaign list rather than dropping the keys', function () {
    [$user, $project] = sender();

    contactable($project);
    $campaign = sequence($project);

    // A key that is simply absent reaches the page as `undefined`, which walks
    // past a null check and prints "Invalid Date". Nobody in the sequence is an
    // ordinary state, not a missing one.
    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('campaigns.0.id', $campaign->id)
            ->where('campaigns.0.live_leads_count', 0)
            ->where('campaigns.0.next_action_at', null));

    app(EnrolCampaign::class)->handle($campaign);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('campaigns.index'))
        ->assertInertia(fn ($page) => $page
            ->where('campaigns.0.live_leads_count', 1)
            ->where('campaigns.0.next_action_at', fn ($due) => $due !== null));
});

it('pauses a running sequence without touching who is in it', function () {
    [$user, $project] = sender();

    contactable($project);
    $campaign = sequence($project);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->put(route('campaigns.status', $campaign), ['status' => 'active']);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->put(route('campaigns.status', $campaign), ['status' => 'paused'])
        ->assertRedirect();

    // The campaign stops being picked up, and nobody is thrown out: resuming
    // has to carry on where the sequence was, not start it again.
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Paused)
        ->and(CampaignLead::query()->count())->toBe(1);
});

it('says when the next mail is owed and what is standing in its way', function () {
    [$user, $project] = sender();

    contactable($project);
    $campaign = sequence($project);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->put(route('campaigns.status', $campaign), ['status' => 'active']);

    $mailbox = EmailAccount::query()->sole();

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('campaigns.delivery', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('campaigns/Delivery')
            // An active campaign that has sent nothing is the normal case, so
            // the screen has to carry the figures that say which case it is.
            ->has('sending.next_action_at')
            ->where('sending.window.start', 8)
            ->where('sending.mailboxes.0.id', $mailbox->id)
            ->where('sending.mailboxes.0.sent_today', 0)
            ->where('sending.mailboxes.0.remaining', $mailbox->allowanceForToday())
            ->where('sending.mailboxes.0.ready_at', null)
            ->where('leadsTotal', 1)
            // Enrolled and not yet written to.
            ->where('leads.0.last_step', 0)
            ->where('leads.0.sent', 0)
            ->where('leads.0.next_action_at', fn ($due) => $due !== null));
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

it('shows where the people in one sequence have got to', function () {
    [$user, $project] = sender();

    contactable($project);
    contactable($project, 'second@friterie.test');
    $campaign = sequence($project);

    app(EnrolCampaign::class)->handle($campaign);
    CampaignLead::query()->limit(1)->update(['status' => CampaignLeadStatus::Stopped, 'pause_reason' => 'bounced']);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('campaigns.delivery', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pipeline.pending', 1)
            ->where('pipeline.stopped', 1));
});

it('sends every mail to one address when a test redirect is configured', function () {
    [, $project] = sender();

    // What a developer sets to try the real loop against their own mailbox.
    config(['eveil.outreach.redirect_to' => 'mydnic@gmail.test']);

    $fake = fakeSender();
    $lead = contactable($project, 'marcel@friterie.test');
    $campaign = sequence($project);

    app(EnrolCampaign::class)->handle($campaign);
    app(SendNextStep::class)->handle(CampaignLead::query()->firstOrFail());

    // The Sender is faked here, so the redirect is asserted where it is decided
    // rather than at the socket.
    expect($fake->sent)->toHaveCount(1);

    $message = Message::query()->sole();

    // What is STORED stays about the lead: the conversation, the inbox and the
    // suppression list all key off them, not off whoever the mail was diverted
    // to. Only the envelope moves.
    expect($message->lead_id)->toBe($lead->id)
        ->and($message->subject)->toBe('vos commandes')
        ->and($lead->refresh()->status)->toBe(OutreachStatus::Contacted);
});

it('puts the intended recipient in the subject of a redirected mail, and leaves an ordinary one alone', function () {
    sender();

    $lead = contactable(Project::query()->firstOrFail(), 'marcel@friterie.test');

    // The real Sender's own decision rather than a copy of it here: this lives in
    // the transport layer, so faking the Sender would assert nothing at all.
    $subjectFor = function (?string $redirect) use ($lead): string {
        config(['eveil.outreach.redirect_to' => $redirect]);

        $sender = new Sender;

        return (new ReflectionMethod($sender, 'subjectFor'))->invoke($sender, $lead, 'vos commandes');
    };

    expect($subjectFor('mydnic@gmail.test'))->toBe('[to: marcel@friterie.test] vos commandes')
        ->and($subjectFor(null))->toBe('vos commandes')
        // An empty string is not an address, and a half-filled env line must not
        // silently divert anything.
        ->and($subjectFor(''))->toBe('vos commandes');
});

it('reads a refusal about the sender as a broken mailbox, never as a dead address', function () {
    [$user, $project, $mailbox] = sender();

    $lead = contactable($project, 'marylene@friterie.test');
    $campaign = sequence($project);
    app(EnrolCampaign::class)->handle($campaign);
    $campaign->update(['status' => CampaignStatus::Active]);

    $fake = fakeSender();
    // Zoho's answer when the From address is not verified on the account. The
    // code is one of the recipient codes, and the words are about the sender.
    $fake->failWith = 'Expected response code "250" but got code "553", with message "553 Sender is not allowed to relay emails".';

    app(SendNextStep::class)->handle(CampaignLead::query()->sole());

    // Suppressing here would burn an innocent prospect for ever over a setting
    // one screen away, and the mail never reached a recipient at all.
    expect($lead->fresh()->status)->toBe(OutreachStatus::New)
        ->and(Suppression::query()->where('layer', SuppressionLayer::Bounce)->count())->toBe(0)
        ->and(Message::query()->sole()->status)->toBe(MessageStatus::Failed);

    // The mailbox is what is broken, and it says so in the server's own words.
    expect($mailbox->fresh()->status)->toBe(EmailAccountStatus::Error)
        ->and($mailbox->fresh()->last_error)->toContain('not allowed to relay');

    unset($user);
});

it('says on every screen that a mailbox has stopped, in the server own words', function () {
    [$user, $project, $mailbox] = sender();

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('setup.broken', 0));

    $mailbox->update([
        'status' => EmailAccountStatus::Error,
        'last_error' => '553 Sender is not allowed to relay emails',
    ]);

    // Nothing else anywhere says so: the campaign stays active, the sequence
    // stays due, and the run simply never moves. The sentence is carried
    // verbatim because it names the setting to change.
    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('setup.broken', 1)
            ->where('setup.broken.0.email', $mailbox->from_email)
            ->where('setup.broken.0.status', 'error')
            ->where('setup.broken.0.error', '553 Sender is not allowed to relay emails'));
});

it('says the same about a mailbox the bounce breaker stopped', function () {
    [$user, $project, $mailbox] = sender();

    $mailbox->update(['status' => EmailAccountStatus::Paused, 'last_error' => null]);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('setup.broken.0.status', 'paused')
            ->where('setup.broken.0.error', null));
});

it('never mentions a mailbox belonging to another project', function () {
    [$user, $project] = sender();

    $stranger = EmailAccount::factory()->create(['status' => EmailAccountStatus::Error]);
    $stranger->projects()->attach(Project::factory()->create());

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('setup.broken', 0));
});

it('sends a real message when testing a mailbox, because the refusal comes after the body', function () {
    [, , $mailbox] = sender();

    $mailbox->update(['from_email' => 'clement@dricle.test', 'smtp_host' => '127.0.0.1', 'smtp_port' => 1]);

    // Measured against a real provider: `MAIL FROM:<not-mine@example.com>` is
    // answered `250 Sender OK` and the refusal only arrives after DATA, because
    // what is checked is the From HEADER and not the envelope. A test that
    // stops before DATA reports a working mailbox that cannot send a thing.
    expect(app(MailboxTester::class)->testSmtp($mailbox))->toContain('SMTP:');
});
