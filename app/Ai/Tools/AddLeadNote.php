<?php

namespace App\Ai\Tools;

use App\Models\Lead;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Logs a note on a contact's timeline - "called today, booked a meeting for
 * the 20th". Never sent anywhere and never read by an agent otherwise: this
 * is the one exception, since Evie is the one writing it on the user's
 * behalf. No approval needed: unlike StartDiscovery/CreateSequence/UpdateSequence,
 * this spawns nothing and costs nothing beyond the message itself.
 */
class AddLeadNote implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Adds a note to a contact\'s timeline. The lead_id comes from GetCompany or GetContact.';
    }

    public function handle(Request $request): Stringable|string
    {
        $lead = Lead::query()
            ->where('project_id', $this->project->id)
            ->whereNull('erased_at')
            ->find($request->integer('lead_id'));

        if ($lead === null) {
            return 'No contact with that id exists on this project (or it was erased). Call GetCompany first.';
        }

        $note = $lead->notes()->create([
            'body' => $request->string('body')->value(),
        ]);

        return "Note {$note->id} added.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()
                ->description('The contact to log this against, from GetCompany or GetContact.')
                ->required(),

            'body' => $schema->string()
                ->description('The note itself, written the way a person would jot it down.')
                ->required(),
        ];
    }
}
