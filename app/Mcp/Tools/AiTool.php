<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ActsOnProject;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool as AiToolContract;
use Laravel\Ai\Tools\Request as AiRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * A project tool (`Toolbelt`), served over MCP as it is: same description,
 * same arguments plus `project`, same answer. Wrapped rather than rewritten,
 * so one class serves both Evie in chat and assistants over MCP.
 *
 * A tool Evie asks the user to approve in chat is flagged destructive, which
 * is what makes an MCP client ask its own user before calling it.
 */
class AiTool extends Tool
{
    use ActsOnProject;

    /**
     * @param  class-string<AiToolContract>  $tool
     */
    public function __construct(string $name, private string $tool)
    {
        $this->name = $name;
        $this->description = (string) $this->instance()->description();
    }

    public function handle(Request $request, CurrentProject $currentProject): Response
    {
        return Response::text((string) $this->instance($currentProject->getOrFail())->handle(
            new AiRequest(Arr::except($request->all(), 'project'))
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $this->projectArgument($schema),
            ...$this->instance()->schema($schema),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function annotations(): array
    {
        return is_subclass_of($this->tool, Approvable::class) ? ['destructiveHint' => true] : [];
    }

    /**
     * Without a project, only for what describes the tool: none of them reads
     * its project before `handle()`.
     */
    private function instance(?Project $project = null): AiToolContract
    {
        return new ($this->tool)($project ?? new Project);
    }
}
