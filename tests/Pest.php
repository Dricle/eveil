<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Enums\CampaignStatus;
use App\Enums\CampaignStepType;
use App\Enums\EmailStatus;
use App\Enums\OutreachStatus;
use App\Models\Campaign;
use App\Models\EmailAccount;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\Outreach\Sender;
use App\Services\Outreach\SendFailure;
use App\Support\CurrentProject;

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
