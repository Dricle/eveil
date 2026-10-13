<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectResource;
use App\Support\TokenScope;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The one tool that needs no project (`CallProjectTool`): it is how the
 * model finds the slugs every other tool asks for.
 */
#[IsReadOnly]
#[Description('List the projects (products or businesses) this connection can reach. Every other tool takes one of their slugs as `project`.')]
class ListProjects extends Tool
{
    public function handle(Request $request, TokenScope $tokenScope): Response|ResponseFactory
    {
        return Response::structured([
            'projects' => ProjectResource::collection($tokenScope->projects()->with('organization')->orderBy('name')->get())->resolve(),
        ]);
    }
}
