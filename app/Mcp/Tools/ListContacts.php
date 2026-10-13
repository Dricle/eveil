<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ContactResource;
use App\Mcp\Tools\Concerns\ActsOnProject;
use App\Mcp\Tools\Concerns\PagesResults;
use App\Models\Lead;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the people (contacts) this project found or imported, oldest first, with their company.')]
class ListContacts extends Tool
{
    use ActsOnProject, PagesResults;

    public function handle(Request $request): ResponseFactory
    {
        return $this->paginated(
            Lead::query()->with('company')->whereNull('erased_at')->orderBy('id'),
            ContactResource::class,
            $request,
        );
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $this->projectArgument($schema),
            'page' => $this->pageArgument($schema),
        ];
    }
}
