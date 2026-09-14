<?php

namespace App\Ai\Tools;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only: one person's detail and their own timeline of notes - "called
 * today, booked a meeting for the 20th". The lead id comes from GetCompany.
 */
class GetContact implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Reads one contact\'s detail (name, title, email, status, company) and their timeline of notes. The lead_id comes from GetCompany.';
    }

    public function handle(Request $request): Stringable|string
    {
        $lead = Lead::query()
            ->where('project_id', $this->project->id)
            ->whereNull('erased_at')
            ->with(['company', 'notes.user'])
            ->find($request->integer('lead_id'));

        if ($lead === null) {
            return 'No contact with that id exists on this project (or it was erased). Call GetCompany first.';
        }

        return (string) json_encode([
            'id' => $lead->id,
            'name' => mb_trim($lead->first_name.' '.$lead->last_name) ?: null,
            'email' => $lead->email,
            'title' => $lead->title,
            'status' => $lead->status->value,
            'company' => $lead->company?->name,
            'notes' => $lead->notes->map(fn (LeadNote $note): array => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->user?->name,
                'at' => $note->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()
                ->description('The contact to read, from GetCompany.')
                ->required(),
        ];
    }
}
