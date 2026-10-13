<?php

namespace App\Mcp\Methods;

use App\Mcp\Tools\ListProjects;
use App\Support\CurrentProject;
use App\Support\TokenScope;
use Generator;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

/**
 * `tools/call`, with the project set first: a token reaches an organization,
 * and every tool but `list-projects` acts on one of its projects, named by
 * the `project` argument (a slug) and checked against `TokenScope`. Done here
 * once, before any tool runs, rather than in each of them: a tool that ran
 * with no project set would have no `BelongsToProject` scope at all, and read
 * every project's rows.
 */
class CallProjectTool extends CallTool
{
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        if (($request->params['name'] ?? null) !== app(ListProjects::class)->name()) {
            $slug = $request->params['arguments']['project'] ?? null;
            $project = app(TokenScope::class)->project(is_string($slug) ? $slug : null);

            if ($project === null) {
                // A tool error the model reads, not a protocol error: it can
                // fix the call itself.
                return JsonRpcResponse::result($request->id, [
                    'content' => [['type' => 'text', 'text' => is_string($slug)
                        ? "No project \"{$slug}\" in reach. Call list-projects for the slugs."
                        : 'Say which project: pass its slug as `project`. Call list-projects for the slugs.']],
                    'isError' => true,
                ]);
            }

            app(CurrentProject::class)->set($project);
        }

        return parent::handle($request, $context);
    }
}
