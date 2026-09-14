<?php

namespace App\Ai\Tools;

use App\Models\Lead;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Removes one note from a contact's timeline. No approval needed: reversible
 * only in the sense that nothing else reads it, same reasoning as
 * `AddLeadNote`.
 */
class DeleteLeadNote implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Deletes one note from a contact\'s timeline.';
    }

    public function handle(Request $request): Stringable|string
    {
        $lead = Lead::query()
            ->where('project_id', $this->project->id)
            ->whereNull('erased_at')
            ->find($request->integer('lead_id'));

        if ($lead === null) {
            return 'No contact with that id exists on this project (or it was erased).';
        }

        $note = $lead->notes()->find($request->integer('note_id'));

        if ($note === null) {
            return 'No note with that id exists on this contact. Call GetContact first.';
        }

        $note->delete();

        return 'Note deleted.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()
                ->description('The contact the note belongs to.')
                ->required(),

            'note_id' => $schema->integer()
                ->description('The note to delete, from GetContact.')
                ->required(),
        ];
    }
}
