<?php

use App\Enums\CampaignLeadStatus;
use App\Enums\MessageDirection;
use App\Enums\OutreachStatus;
use App\Models\CampaignLead;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Message;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

/**
 * @return array{0: User, 1: Project}
 */
function leadOwner(): array
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);

    return [$user, Project::factory()->for($user->organizations()->sole())->create()];
}

it('logs a note on a lead\'s timeline, newest first', function () {
    [$user, $project] = leadOwner();
    $lead = Lead::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->post(route('contacts.notes.store', $lead), ['body' => 'Called today, booked a meeting for the 20th.'])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('contacts.notes.store', $lead), ['body' => 'Second call, confirmed.'])
        ->assertRedirect();

    $this->actingAs($user)->get(route('contacts.show', $lead))
        ->assertInertia(fn ($page) => $page
            ->has('contact.notes', 2)
            ->where('contact.notes.0.body', 'Second call, confirmed.')
            ->where('contact.notes.0.author', $user->name)
            ->where('contact.notes.1.body', 'Called today, booked a meeting for the 20th.'));
});

it('requires a body', function () {
    [$user, $project] = leadOwner();
    $lead = Lead::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->post(route('contacts.notes.store', $lead), ['body' => ''])
        ->assertSessionHasErrors('body');
});

it('deletes a note', function () {
    [$user, $project] = leadOwner();
    $lead = Lead::factory()->create(['project_id' => $project->id]);
    $note = LeadNote::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->delete(route('contacts.notes.destroy', [$lead, $note]))
        ->assertRedirect();

    expect(LeadNote::query()->find($note->id))->toBeNull();
});

it('never lets one project write or delete another project\'s notes', function () {
    [$user] = leadOwner();
    $theirs = Lead::factory()->create();
    $note = LeadNote::factory()->create(['lead_id' => $theirs->id]);

    $this->actingAs($user)
        ->post(route('contacts.notes.store', $theirs), ['body' => 'Sneaky.'])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('contacts.notes.destroy', [$theirs, $note]))
        ->assertNotFound();

    expect(LeadNote::query()->find($note->id))->not->toBeNull();
});

it('wipes a note\'s content when the lead is erased, but keeps the row', function () {
    [, $project] = leadOwner();
    $lead = Lead::factory()->create(['project_id' => $project->id]);
    $note = LeadNote::factory()->create(['lead_id' => $lead->id, 'body' => 'Spoke to Marie about pricing.']);

    $lead->erase();

    expect($note->refresh()->body)->toBe('');
});

it('carries the lead\'s timeline into the inbox\'s side panel too', function () {
    [$user, $project, $mailbox] = sender();
    $lead = contactable($project, 'marcel@friterie.test');
    $lead->update(['status' => OutreachStatus::Replied]);

    $campaign = sequence($project);
    $membership = CampaignLead::query()->create([
        'campaign_id' => $campaign->id,
        'lead_id' => $lead->id,
        'email_account_id' => $mailbox->id,
        'current_step_position' => 1,
        'status' => CampaignLeadStatus::Paused,
    ]);

    Message::query()->create([
        'lead_id' => $lead->id,
        'campaign_lead_id' => $membership->id,
        'email_account_id' => $mailbox->id,
        'direction' => MessageDirection::Inbound,
        'message_id' => 'theirs-1@friterie.test',
        'subject' => 'Re: vos commandes',
        'body' => 'Oui, ca m\'interesse.',
        'received_at' => now(),
    ]);

    LeadNote::factory()->create(['lead_id' => $lead->id, 'body' => 'Appele, interesse.']);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('inbox'))
        ->assertInertia(fn ($page) => $page
            ->has('conversations.data', 1)
            ->where('conversations.data.0.lead.notes.0.body', 'Appele, interesse.'));
});
