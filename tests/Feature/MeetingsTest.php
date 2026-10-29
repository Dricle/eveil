<?php

use App\Ai\Agents\MeetingProposalWriter;
use App\Enums\CampaignLeadStatus;
use App\Enums\MessageDirection;
use App\Enums\ReplyClassification;
use App\Models\AgentRun;
use App\Models\CampaignLead;
use App\Models\EmailAccount;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Services\Meetings\Availability;
use Carbon\CarbonImmutable;

/**
 * A lead who answered "yes, let's talk", in a project whose user takes calls
 * on Tuesday mornings and Thursday afternoons, Brussels time. The clock sits
 * on Monday 2 November 2026, 09:00 in Brussels (UTC+1).
 *
 * @return array{0: User, 1: Project, 2: EmailAccount, 3: CampaignLead}
 */
function interestedConversation(): array
{
    test()->travelTo(CarbonImmutable::parse('2026-11-02 08:00:00', 'UTC'));

    [$user, $project, $mailbox] = sender();
    $mailbox->update(['from_name' => 'Claire Martin', 'from_email' => 'claire@studio.test']);

    $project->update([
        'meeting_timezone' => 'Europe/Brussels',
        'meeting_duration_minutes' => 30,
        'meeting_hours' => [
            ['day' => 2, 'start' => '10:00', 'end' => '12:00'],
            ['day' => 4, 'start' => '14:00', 'end' => '16:00'],
        ],
        'meeting_location' => 'https://meet.example.com/claire',
    ]);

    $lead = contactable($project, 'marcel@friterie.test');
    $lead->update(['first_name' => 'Marcel', 'last_name' => 'Dupont']);

    $membership = CampaignLead::query()->create([
        'campaign_id' => sequence($project)->id,
        'lead_id' => $lead->id,
        'email_account_id' => $mailbox->id,
        'current_step_position' => 1,
        'status' => CampaignLeadStatus::Paused,
    ]);

    Message::factory()->create([
        'lead_id' => $lead->id,
        'campaign_lead_id' => $membership->id,
        'email_account_id' => $mailbox->id,
        'message_id' => 'ours-1@studio.test',
        'subject' => 'vos commandes',
    ]);

    Message::factory()->create([
        'lead_id' => $lead->id,
        'campaign_lead_id' => $membership->id,
        'email_account_id' => $mailbox->id,
        'direction' => MessageDirection::Inbound,
        'message_id' => 'theirs-1@friterie.test',
        'in_reply_to' => 'ours-1@studio.test',
        'subject' => 'Re: vos commandes',
        'body' => 'Oui, appelons-nous la semaine prochaine.',
        'classification' => ReplyClassification::Interested,
        'status' => null,
        'sent_at' => null,
        'received_at' => now(),
    ]);

    forProject($project);

    return [$user, $project, $mailbox, $membership];
}

it('offers one time a day from tomorrow, in the zone the hours were set in', function () {
    [, $project] = interestedConversation();

    $slots = app(Availability::class)->slots($project);

    expect(array_map(fn (CarbonImmutable $slot): string => $slot->toIso8601String(), $slots))->toBe([
        '2026-11-03T09:00:00+00:00',
        '2026-11-05T13:00:00+00:00',
        '2026-11-10T09:00:00+00:00',
    ]);
});

it('offers nothing until the user has said when they take calls', function () {
    [, $project] = interestedConversation();

    $project->update(['meeting_hours' => []]);

    expect(app(Availability::class)->slots($project))->toBe([]);
});

it('drafts an answer offering the free times, without sending anything', function () {
    [$user, , , $membership] = interestedConversation();
    $fake = fakeSender();

    MeetingProposalWriter::fake([['body' => "Bonjour Marcel,\n\nMardi 3 novembre à 10h ?"]]);

    $this->actingAs($user)
        ->postJson(route('inbox.meeting-draft', $membership))
        ->assertOk()
        ->assertExactJson(['body' => "Bonjour Marcel,\n\nMardi 3 novembre à 10h ?"]);

    // The agent is given the times, written out in the user's zone, and
    // their reply to answer: it chooses nothing.
    $prompt = (string) (AgentRun::query()->where('agent', 'meeting-proposal-writer')->sole()->input['prompt'] ?? '');

    expect($prompt)->toContain('Tuesday 3 November 2026, 10:00')
        ->toContain('Thursday 5 November 2026, 14:00')
        ->toContain('appelons-nous')
        ->toContain('https://meet.example.com/claire')
        ->and($fake->sent)->toBe([]);
});

it('says why it cannot draft when no meeting hours are set, without calling the agent', function () {
    [$user, $project, , $membership] = interestedConversation();
    $project->update(['meeting_hours' => null]);

    MeetingProposalWriter::fake();

    $this->actingAs($user)
        ->postJson(route('inbox.meeting-draft', $membership))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('draft');

    MeetingProposalWriter::assertNeverPrompted();
});

it('does not draft for a conversation in another project', function () {
    [$user, , , $membership] = interestedConversation();

    $other = Project::factory()->for($user->organizations()->sole())->create();
    forProject($other);

    MeetingProposalWriter::fake();

    $this->actingAs($user)
        ->postJson(route('inbox.meeting-draft', ['project' => $other->slug, 'conversation' => $membership->id]))
        ->assertNotFound();

    MeetingProposalWriter::assertNeverPrompted();
});

it('saves the hours the user takes calls', function () {
    [$user, $project] = interestedConversation();

    $this->actingAs($user)
        ->putJson(route('settings.meetings.update'), [
            'meeting_duration_minutes' => 45,
            'meeting_timezone' => 'America/Montreal',
            'meeting_hours' => [['day' => 1, 'start' => '09:00', 'end' => '11:30']],
            'meeting_location' => null,
        ])
        ->assertRedirect(route('settings.meetings.edit'));

    expect($project->refresh()->meeting_duration_minutes)->toBe(45)
        ->and($project->meeting_timezone)->toBe('America/Montreal')
        ->and($project->meeting_hours)->toEqual([['day' => 1, 'start' => '09:00', 'end' => '11:30']])
        ->and($project->meeting_location)->toBeNull();
});

it('refuses hours that end before they start, or a zone that does not exist', function () {
    [$user, $project] = interestedConversation();

    $this->actingAs($user)
        ->putJson(route('settings.meetings.update'), [
            'meeting_duration_minutes' => 30,
            'meeting_timezone' => 'Europe/Atlantis',
            'meeting_hours' => [['day' => 1, 'start' => '11:00', 'end' => '09:00']],
        ])
        ->assertJsonValidationErrors(['meeting_timezone', 'meeting_hours.0.end']);

    expect($project->refresh()->meeting_timezone)->toBe('Europe/Brussels');
});
