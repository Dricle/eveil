<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ContactStoreRequest;
use App\Imports\LeadsImport;
use App\Mcp\Tools\Concerns\ActsOnProject;
use App\Support\CurrentProject;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * `POST /api/v1/contacts`: the CSV import without the file, validated by the
 * same rules and answered with the same per-row report.
 */
#[Description('Add people you already know to this project, up to 500 at a time. Each needs an email or a LinkedIn URL. Duplicates, people who asked to be forgotten and suppressed addresses are skipped; the answer reports every row.')]
class AddContacts extends Tool
{
    use ActsOnProject;

    public function handle(Request $request, CurrentProject $currentProject): Response|ResponseFactory
    {
        $import = new LeadsImport($currentProject->getOrFail());

        foreach ($request->validate((new ContactStoreRequest)->rules())['contacts'] as $index => $contact) {
            $import->add($contact, $index + 1);
        }

        return Response::structured($import->report());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $columns = collect(LeadsImport::COLUMNS)
            ->mapWithKeys(fn (string $column) => [$column => $schema->string()->nullable()])
            ->all();

        return [
            'project' => $this->projectArgument($schema),
            'contacts' => $schema->array()
                ->items($schema->object($columns)->withoutAdditionalProperties())
                ->max(500)
                ->required(),
        ];
    }
}
