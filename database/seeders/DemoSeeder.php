<?php

namespace Database\Seeders;

use App\Actions\CreateAccount;
use App\Enums\AgentRunStatus;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisType;
use App\Enums\CampaignLeadStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignStepType;
use App\Enums\ContactSearchStatus;
use App\Enums\DiscoveryDiagnosis;
use App\Enums\DiscoveryRunOrigin;
use App\Enums\DiscoveryRunStatus;
use App\Enums\EmailSource;
use App\Enums\EmailStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\OutreachStatus;
use App\Enums\ReplyClassification;
use App\Enums\SuppressionLayer;
use App\Enums\TargetProfileSource;
use App\Enums\TargetProfileType;
use App\Models\AgentRun;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\CampaignStep;
use App\Models\Company;
use App\Models\CompanyTargetEvaluation;
use App\Models\DiscoveryRun;
use App\Models\EmailAccount;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectAnalysis;
use App\Models\StepVariant;
use App\Models\Suppression;
use App\Models\TargetProfile;
use App\Support\CurrentProject;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A fully populated instance for local development: a login, a project with a
 * knowledge base, target profiles, past discovery runs, qualified companies
 * and their contacts, campaigns mid flight, and an inbox with real replies to
 * look at. Nothing here calls a model: every "AI" output is hand written,
 * shaped like what the real agents return, so it works offline and for free.
 *
 * Not part of `InstallSeeder`: a fresh production install must not get demo
 * data. Run by hand: `sail artisan db:seed --class=Database\\Seeders\\DemoSeeder`.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(InstallSeeder::class);

        $user = app(CreateAccount::class)->handle([
            'name' => 'Demo User',
            'email' => 'demo@eveil.test',
            'password' => 'password',
            'organization' => 'Eveil Demo',
        ], isSuperAdmin: true);

        $organization = $user->organizations()->sole();

        $project = Project::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Cargo',
            'url' => 'https://cargo.example',
            'default_language' => 'en',
            'knowledge_base' => $this->knowledgeBase(),
            'knowledge_base_edited_by_user' => false,
        ]);

        app(CurrentProject::class)->run($project, function () use ($project, $organization): void {
            $mailboxes = $this->mailboxes($project, $organization);
            $profiles = $this->targetProfiles($project);

            $this->discoveryRuns($project, $profiles);

            $companies = $this->companies($project, $profiles);
            $leads = $this->leads($project, $companies);

            $campaigns = $this->campaigns($project, $profiles);
            $this->enrol($campaigns, $leads, $mailboxes);

            $this->agentRuns($project);
            $this->projectAnalysis($project);
        });

        $this->command->info('Demo ready: log in as demo@eveil.test / password');
    }

    /**
     * @return array<string, mixed>
     */
    private function knowledgeBase(): array
    {
        return [
            'what_it_does' => 'Cargo is project and time tracking built around client work: every board, task and'
                .' timer rolls up into a billable report a client can be sent directly.',
            'who_it_is_for' => 'Small and mid sized agencies (design, development, marketing) who bill clients by'
                .' the hour or by retainer and currently stitch together a task board and a separate time tracker.',
            'value_proposition' => 'One place to plan the work, track the hours and bill the client, instead of a'
                .' task board, a timer and a spreadsheet that all drift out of sync.',
            'positioning' => 'Positioned against running Asana or Trello alongside Harvest or Toggl: Cargo argues'
                .' that splitting planning from billing is what makes agencies chase timesheets every Friday.',
            'key_features' => [
                'Kanban and list boards per client',
                'Built in timer with idle detection',
                'Client facing billing reports, sent straight from a task board',
                'Retainer budgets with an automatic burn down alert',
                'Slack and calendar integrations',
            ],
            'pricing_model' => 'Per seat, monthly or annual, with a 14 day trial. No client seats charged.',
            'competitors' => ['Harvest', 'Productive', 'Runn'],
            'proof_points' => [
                '340 agencies onboarded in the last year',
                'Case study: a 12 person branding studio cut billing prep from a day to twenty minutes',
            ],
            'language' => 'en',
            'confidence' => 88,
            'gaps' => [
                ['key' => 'deployment_model', 'question' => 'Is there a self hosted option, or is Cargo cloud only?', 'answer' => null],
                ['key' => 'minimum_seats', 'question' => 'Is there a minimum seat count, or does it work for a two person shop?', 'answer' => 'No minimum, priced per seat from one.'],
            ],
            'recommendations' => [
                [
                    'key' => 'agency_case_studies',
                    'idea' => 'Publish one case study per agency size band (2 to 5, 10 to 20, 30 plus): the single'
                        .' case study on site is for a 12 person shop, which tells a two person studio nothing about'
                        .' whether Cargo fits them.',
                    'evidence' => 'The site names one proof point and it is a mid sized studio; nothing on site'
                        .' addresses a very small or a large agency.',
                    'impact' => 'high',
                    'effort' => 'medium',
                ],
                [
                    'key' => 'referral_program',
                    'idea' => 'A referral credit for agencies that bring in another agency: this segment refers'
                        .' inside its own trade associations constantly and nothing on site rewards it.',
                    'evidence' => 'No referral or partner program is mentioned anywhere on the site.',
                    'impact' => 'medium',
                    'effort' => 'low',
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, EmailAccount>
     */
    private function mailboxes(Project $project, Organization $organization): Collection
    {
        $mailboxes = collect([
            ['name' => 'Alex, Sales', 'from_name' => 'Alex Moreau', 'from_email' => 'alex@cargo.example'],
            ['name' => 'Sam, Growth', 'from_name' => 'Sam Okafor', 'from_email' => 'sam@cargo.example'],
        ])->map(fn (array $data): EmailAccount => EmailAccount::factory()->create([
            'organization_id' => $organization->id,
            'name' => $data['name'],
            'from_name' => $data['from_name'],
            'from_email' => $data['from_email'],
            'smtp_username' => $data['from_email'],
            'imap_username' => $data['from_email'],
            'daily_limit' => 40,
        ]));

        $project->emailAccounts()->attach($mailboxes->pluck('id'));

        return $mailboxes;
    }

    /**
     * @return Collection<int, TargetProfile>
     */
    private function targetProfiles(Project $project): Collection
    {
        $agencies = TargetProfile::factory()->create([
            'project_id' => $project->id,
            'name' => 'Digital agencies, 10 to 50 people',
            'type' => TargetProfileType::Customer,
            'source' => TargetProfileSource::Agent,
            'is_active' => true,
            'criteria' => [
                'rationale' => 'They bill clients hourly or on retainer and already juggle a task board and a'
                    .' separate timer. Cargo removes the reconciliation between the two.',
                'company_size' => '10 to 50 people, one or two offices.',
                'estimated_market_size' => 'Roughly 6,000 agencies of this size across Western Europe, estimated'
                    .' from national design and marketing association directories.',
                'sectors' => ['web development', 'digital marketing', 'branding and design'],
                'geography' => ['Belgium', 'France', 'Netherlands', 'United Kingdom'],
                'job_titles' => ['Founder', 'Agency owner', 'Operations director', 'Studio manager'],
                'technologies' => ['Asana', 'Trello', 'Harvest', 'Toggl'],
                'trigger_signals' => ['Recently hired an operations or studio manager', 'Posting for a new account manager role'],
                'search_queries' => ['digital agency team page', 'branding studio our team', 'marketing agency careers'],
                'confidence' => 82,
            ],
        ]);

        $freelancers = TargetProfile::factory()->create([
            'project_id' => $project->id,
            'name' => 'Freelance creative consultants',
            'type' => TargetProfileType::Customer,
            'source' => TargetProfileSource::Human,
            'is_active' => true,
            'criteria' => [
                'rationale' => 'Solo consultants bill by the hour too, but most tools this size are aimed at teams'
                    .' and overcharge per seat for one person.',
                'company_size' => 'One person, occasionally with a subcontractor.',
                'estimated_market_size' => 'Not sized yet: too fragmented to count from a directory.',
                'sectors' => ['freelance design', 'freelance development', 'independent marketing consulting'],
                'geography' => ['European Union'],
                'job_titles' => ['Freelancer', 'Independent consultant'],
                'technologies' => [],
                'trigger_signals' => ['New freelance portfolio site published'],
                'search_queries' => ['freelance designer portfolio', 'independent web developer available'],
                'confidence' => 55,
            ],
        ]);

        return collect([$agencies, $freelancers]);
    }

    /**
     * Past runs, hand written rather than executed: what a screen shows once a
     * few searches have already happened, without paying for the searches.
     *
     * @param  Collection<int, TargetProfile>  $profiles
     */
    private function discoveryRuns(Project $project, Collection $profiles): void
    {
        [$agencies, $freelancers] = $profiles;

        DiscoveryRun::factory()->create([
            'project_id' => $project->id,
            'target_profile_id' => $agencies->id,
            'origin' => DiscoveryRunOrigin::Search,
            'status' => DiscoveryRunStatus::Succeeded,
            'budget' => ['max_companies' => 40, 'max_qualified' => 25, 'max_pages' => 60, 'max_queries' => 12],
            'queries_used' => 9,
            'candidates_found' => 22,
            'pages_used' => 31,
            'qualified_count' => 14,
            'stats' => ['plan' => 'Search web directories and agency team pages across Belgium, France and the Netherlands for digital, branding and marketing agencies.'],
            'started_at' => now()->subDays(9),
            'finished_at' => now()->subDays(9)->addMinutes(11),
        ]);

        DiscoveryRun::factory()->create([
            'project_id' => $project->id,
            'target_profile_id' => $agencies->id,
            'origin' => DiscoveryRunOrigin::Search,
            'status' => DiscoveryRunStatus::Exhausted,
            'diagnosis' => DiscoveryDiagnosis::TooNarrow,
            'budget' => ['max_companies' => 40, 'max_qualified' => 25, 'max_pages' => 60, 'max_queries' => 12],
            'queries_used' => 12,
            'candidates_found' => 10,
            'pages_used' => 18,
            'qualified_count' => 4,
            'stats' => ['plan' => 'Widen to United Kingdom agencies after the Benelux search came back thin.'],
            'started_at' => now()->subDays(3),
            'finished_at' => now()->subDays(3)->addMinutes(7),
        ]);

        DiscoveryRun::factory()->create([
            'project_id' => $project->id,
            'target_profile_id' => $agencies->id,
            'origin' => DiscoveryRunOrigin::Manual,
            'status' => DiscoveryRunStatus::Succeeded,
            'budget' => ['max_companies' => 40, 'max_qualified' => 25, 'max_pages' => 60, 'max_queries' => 12],
            'candidates_found' => 2,
            'pages_used' => 2,
            'qualified_count' => 2,
            'stats' => ['plan' => '2 links submitted by the user.'],
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay()->addMinutes(2),
        ]);

        DiscoveryRun::factory()->create([
            'project_id' => $project->id,
            'target_profile_id' => $freelancers->id,
            'origin' => DiscoveryRunOrigin::Search,
            'status' => DiscoveryRunStatus::Succeeded,
            'budget' => ['max_companies' => 40, 'max_qualified' => 25, 'max_pages' => 60, 'max_queries' => 12],
            'queries_used' => 8,
            'candidates_found' => 15,
            'pages_used' => 20,
            'qualified_count' => 6,
            'stats' => ['plan' => 'Search freelance portfolio sites and directories across the EU.'],
            'started_at' => now()->subDays(5),
            'finished_at' => now()->subDays(5)->addMinutes(9),
        ]);
    }

    /**
     * @param  Collection<int, TargetProfile>  $profiles
     * @return Collection<int, Company>
     */
    private function companies(Project $project, Collection $profiles): Collection
    {
        [$agencies, $freelancers] = $profiles;

        $named = [
            ['name' => 'Pixel & Rye', 'domain' => 'pixelandrye.com', 'city' => 'Ghent', 'size' => '18 people', 'source' => 'web_search'],
            ['name' => 'Northwind Studio', 'domain' => 'northwindstudio.be', 'city' => 'Brussels', 'size' => '24 people', 'source' => 'overpass'],
            ['name' => 'Foundry Digital', 'domain' => 'foundrydigital.fr', 'city' => 'Lyon', 'size' => '31 people', 'source' => 'web_search'],
            ['name' => 'Marbleworks', 'domain' => 'marbleworks.nl', 'city' => 'Utrecht', 'size' => '12 people', 'source' => 'directory'],
            ['name' => 'Redshift Branding', 'domain' => 'redshiftbranding.com', 'city' => 'London', 'size' => '45 people', 'source' => 'web_search'],
            ['name' => 'Loop Nine', 'domain' => 'loopnine.be', 'city' => 'Antwerp', 'size' => '9 people', 'source' => 'user-submitted'],
            ['name' => 'Marchetype', 'domain' => 'marchetype.fr', 'city' => 'Paris', 'size' => '27 people', 'source' => 'web_search'],
            ['name' => 'Studio Verlaine', 'domain' => 'studioverlaine.nl', 'city' => 'Amsterdam', 'size' => '15 people', 'source' => 'overpass'],
            ['name' => 'Anchorpoint', 'domain' => 'anchorpoint.be', 'city' => 'Liege', 'size' => '11 people', 'source' => 'user-submitted'],
        ];

        $companies = collect($named)->map(fn (array $data): Company => Company::factory()->create([
            'project_id' => $project->id,
            'domain' => $data['domain'],
            'name' => $data['name'],
            'website' => 'https://'.$data['domain'],
            'industry' => 'Digital agency',
            'size' => $data['size'],
            'location' => $data['city'],
            'language' => fake()->randomElement(['en', 'fr', 'nl']),
            'facts' => ['city' => $data['city']],
            'source' => $data['source'],
            'discovered_at' => now()->subDays(fake()->numberBetween(1, 12)),
        ]));

        $companies = $companies->merge(Company::factory()->count(6)->create([
            'project_id' => $project->id,
            'industry' => 'Digital agency',
            'source' => fake()->randomElement(['web_search', 'overpass', 'directory']),
            'discovered_at' => now()->subDays(fake()->numberBetween(1, 12)),
        ]));

        $companies = $companies->merge(Company::factory()->count(4)->create([
            'project_id' => $project->id,
            'industry' => 'Freelance consulting',
            'size' => '1 person',
            'source' => 'web_search',
            'discovered_at' => now()->subDays(fake()->numberBetween(1, 8)),
        ]));

        // Fit score is per (company, profile), never on the company: the
        // agency profile scores every company here, the freelancer profile
        // only the four sized for one person.
        $companies->each(function (Company $company, int $index) use ($agencies, $freelancers): void {
            $isFreelancer = $company->size === '1 person';

            CompanyTargetEvaluation::factory()->create([
                'company_id' => $company->id,
                'target_profile_id' => $isFreelancer ? $freelancers->id : $agencies->id,
                'fit_score' => $isFreelancer ? fake()->numberBetween(40, 75) : fake()->numberBetween(55, 96),
                'fit_reason' => $isFreelancer
                    ? "{$company->name} is a solo consultant billing clients by the hour with no billing tool named on their site."
                    : "{$company->name} is a {$company->size} agency running client work through a task board with no billing integration mentioned.",
            ]);

            // A third of the agencies were also scored against the freelancer
            // profile early on, before the profile's own segment (individual
            // sites) proved a better fit: the overlap two profiles finding the
            // same company are supposed to record.
            if (! $isFreelancer && $index % 3 === 0) {
                CompanyTargetEvaluation::factory()->create([
                    'company_id' => $company->id,
                    'target_profile_id' => $freelancers->id,
                    'fit_score' => fake()->numberBetween(15, 35),
                    'fit_reason' => "{$company->name} is a team, not a solo consultant: weak fit for this segment.",
                ]);
            }

            $status = [
                OutreachStatus::New, OutreachStatus::New, OutreachStatus::Contacted,
                OutreachStatus::Contacted, OutreachStatus::Replied, OutreachStatus::Won,
                OutreachStatus::Lost, OutreachStatus::New, OutreachStatus::Rejected,
            ][$index % 9];

            $approved = ! in_array($status, [OutreachStatus::New], true) || $index % 4 === 0;
            $contactsStatus = $index % 5 === 0 ? null : ContactSearchStatus::Done;

            $company->update([
                'status' => $status,
                'approved_at' => $approved ? now()->subDays(fake()->numberBetween(1, 10)) : null,
                'contacts_status' => $contactsStatus,
                'contacts_searched_at' => $contactsStatus === ContactSearchStatus::Done ? now()->subDays(fake()->numberBetween(1, 9)) : null,
            ]);
        });

        return $companies;
    }

    /**
     * @param  Collection<int, Company>  $companies
     * @return Collection<int, Lead>
     */
    private function leads(Project $project, Collection $companies): Collection
    {
        return $companies
            ->filter(fn (Company $company): bool => $company->contacts_status === ContactSearchStatus::Done)
            ->flatMap(function (Company $company) use ($project): array {
                $count = $company->size === '1 person' ? 1 : fake()->numberBetween(1, 2);

                return Lead::factory()->count($count)->create([
                    'project_id' => $project->id,
                    'company_id' => $company->id,
                    'title' => $company->size === '1 person' ? 'Founder' : fake()->randomElement(['Founder', 'Operations director', 'Studio manager', 'Agency owner']),
                    'email_status' => fake()->randomElement([EmailStatus::Valid, EmailStatus::Valid, EmailStatus::Valid, EmailStatus::Risky, EmailStatus::Unknown]),
                    'email_source' => fake()->randomElement([EmailSource::Scraped, EmailSource::Inferred]),
                    'language' => $company->language,
                    'source' => $company->source,
                    'status' => $company->status,
                    'discovered_at' => $company->discovered_at,
                ])->all();
            });
    }

    /**
     * @param  Collection<int, TargetProfile>  $profiles
     * @return Collection<int, Campaign>
     */
    private function campaigns(Project $project, Collection $profiles): Collection
    {
        [$agencies, $freelancers] = $profiles;

        $outreach = Campaign::factory()->create([
            'project_id' => $project->id,
            'target_profile_id' => $agencies->id,
            'name' => 'Agencies, cold outreach',
            'status' => CampaignStatus::Active,
        ]);

        $this->steps($outreach, [
            ['type' => CampaignStepType::Email, 'intent' => 'Introduce Cargo and name the exact problem: planning and billing living in two tools.', 'subject' => 'Quick one about how {{company}} bills clients', 'body' => "Hi {{first_name}},\n\nI noticed {{company}} runs client work through a task board, and I'm guessing hours still get tracked somewhere else.\n\nCargo puts both in one place: the board a project manager already uses becomes the timesheet a client gets billed from. No more reconciling a task board against a spreadsheet on a Friday afternoon.\n\nWorth a fifteen minute look?\n\nAlex"],
            ['type' => CampaignStepType::Wait, 'delay_hours' => 72],
            ['type' => CampaignStepType::Email, 'intent' => 'Follow up with a concrete result from a similarly sized agency.', 'subject' => 'Re: Quick one about how {{company}} bills clients', 'body' => "Hi {{first_name}},\n\nFollowing up in case this got buried. One agency about {{company}}'s size cut their billing prep from a full day to twenty minutes after moving onto Cargo.\n\nHappy to show you the same setup on a short call this week.\n\nAlex"],
            ['type' => CampaignStepType::Wait, 'delay_hours' => 96],
            ['type' => CampaignStepType::Email, 'intent' => 'Last message in the sequence, low pressure, easy to ignore.', 'subject' => 'Closing the loop', 'body' => "Hi {{first_name}},\n\nI'll leave this here rather than keep following up. If billing and planning start feeling disconnected again, Cargo will be around.\n\nAll the best,\nAlex"],
        ]);

        $freelancerCampaign = Campaign::factory()->create([
            'project_id' => $project->id,
            'target_profile_id' => $freelancers->id,
            'name' => 'Freelancers, cold outreach',
            'status' => CampaignStatus::Draft,
        ]);

        $this->steps($freelancerCampaign, [
            ['type' => CampaignStepType::Email, 'intent' => 'Speak to a solo consultant, not a team: acknowledge most tools this size overcharge per seat.', 'subject' => 'A billing tool sized for one person', 'body' => "Hi {{first_name}},\n\nMost project tools price for a team, which is a strange thing to charge a solo consultant for.\n\nCargo tracks the hours and turns them into a client ready report, priced for exactly one seat.\n\nWorth a look?\n\nSam"],
            ['type' => CampaignStepType::Wait, 'delay_hours' => 72],
            ['type' => CampaignStepType::Email, 'intent' => 'Short, direct follow up.', 'subject' => 'Re: A billing tool sized for one person', 'body' => "Hi {{first_name}},\n\nStill happy to show you the fifteen minute version whenever suits.\n\nSam"],
        ]);

        $warm = Campaign::factory()->create([
            'project_id' => $project->id,
            'target_profile_id' => null,
            'name' => 'Warm re-engagement',
            'status' => CampaignStatus::Paused,
        ]);

        $this->steps($warm, [
            ['type' => CampaignStepType::Email, 'intent' => 'Hand composed check in for leads who went quiet a while ago.', 'subject' => 'Checking back in', 'body' => "Hi {{first_name}},\n\nIt's been a while since we last spoke. Has anything changed on the billing side at {{company}}?\n\nAlex"],
            ['type' => CampaignStepType::Wait, 'delay_hours' => 120],
        ]);

        return collect([$outreach, $freelancerCampaign, $warm]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function steps(Campaign $campaign, array $steps): void
    {
        foreach (array_values($steps) as $index => $step) {
            /** @var CampaignStep $created */
            $created = $campaign->steps()->create([
                'position' => $index + 1,
                'type' => $step['type'],
                'delay_hours' => $step['type'] === CampaignStepType::Wait ? $step['delay_hours'] : null,
                'config' => $step['type'] === CampaignStepType::Email ? ['intent' => $step['intent']] : null,
            ]);

            if ($step['type'] === CampaignStepType::Email) {
                StepVariant::factory()->create([
                    'campaign_step_id' => $created->id,
                    'subject' => $step['subject'],
                    'body' => $step['body'],
                    'language' => null,
                ]);
            }
        }
    }

    /**
     * Campaign memberships, mid sequence and finished, replied and silent.
     * What the pipeline funnel and the inbox actually show once a campaign has
     * been running a while, rather than the empty state of one just switched on.
     *
     * @param  Collection<int, Campaign>  $campaigns
     * @param  Collection<int, Lead>  $leads
     * @param  Collection<int, EmailAccount>  $mailboxes
     */
    private function enrol(Collection $campaigns, Collection $leads, Collection $mailboxes): void
    {
        [$outreach, , $warm] = $campaigns;
        $mailbox = $mailboxes->first();

        $pool = $leads->filter(fn (Lead $lead): bool => $lead->email !== null)->values();

        // An arrow function auto-captures by value, so shrinking `$pool` needs
        // an explicit reference: without it every call would hand back the
        // same first N leads instead of moving through the list.
        $take = function (int $count) use (&$pool): Collection {
            $slice = $pool->take($count);
            $pool = $pool->skip($count)->values();

            return $slice;
        };

        // Finished the sequence, nobody answered: the ordinary, unremarkable
        // outcome most sends end in.
        foreach ($take(2) as $lead) {
            $membership = $this->membership($outreach, $lead, $mailbox, CampaignLeadStatus::Completed, position: 5);
            $this->sendSteps($outreach, $membership, $mailbox, upTo: 3);
        }

        // Mid sequence, waiting on the next step.
        foreach ($take(2) as $lead) {
            $membership = $this->membership($outreach, $lead, $mailbox, CampaignLeadStatus::Running, position: 2, nextActionAt: now()->addHours(fake()->numberBetween(6, 48)));
            $this->sendSteps($outreach, $membership, $mailbox, upTo: 1);
        }

        // Every reply outcome the agent can hand back, so the inbox and the
        // pipeline both show real variety.
        $outcomes = [
            'interested' => [CampaignLeadStatus::Paused, 'awaiting_human', ReplyClassification::Interested, OutreachStatus::Replied],
            'not_interested' => [CampaignLeadStatus::Stopped, 'not_interested', ReplyClassification::NotInterested, OutreachStatus::Lost],
            'not_now' => [CampaignLeadStatus::Paused, 'not_now', ReplyClassification::NotNow, OutreachStatus::Replied],
            'wrong_person' => [CampaignLeadStatus::Stopped, 'wrong_person', ReplyClassification::WrongPerson, OutreachStatus::Replied],
            'auto_reply' => [CampaignLeadStatus::Running, null, ReplyClassification::AutoReply, null],
            'unsubscribe' => [CampaignLeadStatus::Stopped, 'unsubscribed', ReplyClassification::Unsubscribe, OutreachStatus::Suppressed],
        ];

        $replyBodies = [
            'interested' => "Hi, this actually lands at a good time, we've been tracking hours in a spreadsheet since our last tool fell apart. Can you send some times this week?",
            'not_interested' => "Thanks, but we're happy with our current setup for now.",
            'not_now' => 'Not the right time, we just signed a lease on a new office and everything is on hold until spring. Try me again then.',
            'wrong_person' => "I'm not the right person for this, you'd want to speak to our studio manager instead.",
            'auto_reply' => "I'm out of the office until Monday with limited access to email. I'll respond as soon as I'm back.",
            'unsubscribe' => 'Please remove me from your list, not interested in being contacted again.',
        ];

        foreach ($outcomes as $key => [$status, $pauseReason, $classification, $leadStatus]) {
            $lead = $take(1)->first();

            if ($lead === null) {
                continue;
            }

            $membership = $this->membership($outreach, $lead, $mailbox, $status, position: 1, pauseReason: $pauseReason);
            $sent = $this->sendSteps($outreach, $membership, $mailbox, upTo: 1);
            $reply = $this->reply($lead, $membership, $mailbox, $sent, $classification, $replyBodies[$key]);

            // The real `ReplyOutcomes` methods stamp `paused_at` at the moment
            // the reply is read, not at enrolment: kept in step here so the
            // pause reads as caused by the reply beside it, not by an
            // arbitrary earlier hour.
            if ($reply !== null && in_array($status, [CampaignLeadStatus::Paused, CampaignLeadStatus::Stopped], true)) {
                $membership->update(['paused_at' => $reply->received_at]);
            }

            if ($leadStatus !== null) {
                $lead->update(['status' => $leadStatus]);
            }

            if ($key === 'unsubscribe' && $lead->email !== null) {
                Suppression::factory()->create([
                    'layer' => SuppressionLayer::OptOut,
                    'project_id' => $lead->project_id,
                    'organization_id' => $mailbox->organization_id,
                    'email' => mb_strtolower($lead->email),
                    'reason' => 'replied asking not to be contacted',
                    'source' => 'reply',
                ]);
            }
        }

        // The paused, hand composed campaign: reached out once, sitting quiet
        // until somebody flips it back on.
        foreach ($take(2) as $lead) {
            $membership = $this->membership($warm, $lead, $mailbox, CampaignLeadStatus::Running, position: 1);
            $this->sendSteps($warm, $membership, $mailbox, upTo: 1);
        }
    }

    private function membership(
        Campaign $campaign,
        Lead $lead,
        EmailAccount $mailbox,
        CampaignLeadStatus $status,
        int $position,
        ?CarbonImmutable $nextActionAt = null,
        ?string $pauseReason = null,
    ): CampaignLead {
        return CampaignLead::factory()->create([
            'campaign_id' => $campaign->id,
            'lead_id' => $lead->id,
            'email_account_id' => $mailbox->id,
            'current_step_position' => $position,
            'status' => $status,
            'next_action_at' => $nextActionAt,
            'pause_reason' => $pauseReason,
        ]);
    }

    /**
     * Sends the outbound mail for however many email steps this membership has
     * reached, oldest first, so the thread reads the way it was actually sent.
     *
     * @return array<int, Message>
     */
    private function sendSteps(Campaign $campaign, CampaignLead $membership, EmailAccount $mailbox, int $upTo): array
    {
        $emailSteps = $campaign->steps->where('type', CampaignStepType::Email)->values()->take($upTo);
        $sent = [];

        foreach ($emailSteps as $index => $step) {
            $variant = $step->variants->first();

            $sent[] = Message::factory()->create([
                'lead_id' => $membership->lead_id,
                'campaign_lead_id' => $membership->id,
                'email_account_id' => $mailbox->id,
                'step_variant_id' => $variant->id,
                'direction' => MessageDirection::Outbound,
                'message_id' => Str::uuid().'@'.Str::after($mailbox->from_email, '@'),
                'subject' => $variant->subject,
                'body' => $variant->body,
                'status' => MessageStatus::Sent,
                'sent_at' => now()->subDays((count($emailSteps) - $index) * 3),
            ]);
        }

        return $sent;
    }

    /**
     * @param  array<int, Message>  $sent
     */
    private function reply(Lead $lead, CampaignLead $membership, EmailAccount $mailbox, array $sent, ReplyClassification $classification, string $body): ?Message
    {
        $original = end($sent);

        if ($original === false) {
            return null;
        }

        return Message::factory()->create([
            'lead_id' => $lead->id,
            'campaign_lead_id' => $membership->id,
            'email_account_id' => $mailbox->id,
            'direction' => MessageDirection::Inbound,
            'message_id' => Str::uuid().'@'.Str::after((string) $lead->email, '@'),
            'in_reply_to' => $original->message_id,
            'subject' => 'Re: '.$original->subject,
            'body' => $body,
            'status' => null,
            'sent_at' => null,
            'received_at' => $original->sent_at->addHours(fake()->numberBetween(2, 30)),
            'classification' => $classification,
        ]);
    }

    private function agentRuns(Project $project): void
    {
        $runs = [
            ['agent' => 'website-analyst', 'tokens_in' => 4200, 'tokens_out' => 1800],
            ['agent' => 'target-profile-deriver', 'tokens_in' => 4456, 'tokens_out' => 4833],
            ['agent' => 'discovery-planner', 'tokens_in' => 2100, 'tokens_out' => 900],
            ['agent' => 'result-triage', 'tokens_in' => 1800, 'tokens_out' => 400],
            ['agent' => 'company-qualifier', 'tokens_in' => 1200, 'tokens_out' => 300],
            ['agent' => 'company-qualifier', 'tokens_in' => 1150, 'tokens_out' => 280],
            ['agent' => 'contact-extractor', 'tokens_in' => 900, 'tokens_out' => 220],
            ['agent' => 'sequence-writer', 'tokens_in' => 3600, 'tokens_out' => 2100],
            ['agent' => 'message-personalizer', 'tokens_in' => 1400, 'tokens_out' => 500],
            ['agent' => 'message-personalizer', 'tokens_in' => 1380, 'tokens_out' => 480],
            ['agent' => 'reply-handler', 'tokens_in' => 800, 'tokens_out' => 150],
            ['agent' => 'reply-handler', 'tokens_in' => 820, 'tokens_out' => 160],
            ['agent' => 'company-qualifier', 'tokens_in' => 1100, 'tokens_out' => 0, 'status' => AgentRunStatus::Failed, 'error' => 'Provider timed out after 60s.'],
        ];

        // Inserted oldest first so ids grow with time: the dashboard's "recent"
        // list orders by id, and a row inserted last must also be the most
        // recent one, or the list would show its history backwards.
        foreach ($runs as $index => $run) {
            AgentRun::factory()->create([
                'project_id' => $project->id,
                'agent' => $run['agent'],
                'status' => $run['status'] ?? AgentRunStatus::Succeeded,
                'tokens_in' => $run['tokens_in'],
                'tokens_out' => $run['tokens_out'],
                'error' => $run['error'] ?? null,
                'created_at' => now()->subHours((count($runs) - $index - 1) * 5),
            ]);
        }
    }

    private function projectAnalysis(Project $project): void
    {
        ProjectAnalysis::factory()->create([
            'project_id' => $project->id,
            'type' => AnalysisType::Website,
            'status' => AnalysisStatus::Succeeded,
            'summary' => $this->knowledgeBase(),
        ]);
    }
}
