<?php

namespace App\Mcp\Tools\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * The `project` argument every tool but `list-projects` takes. Read and
 * checked by `CallProjectTool` before the tool runs, never by the tool.
 */
trait ActsOnProject
{
    protected function projectArgument(JsonSchema $schema): Type
    {
        return $schema->string()->description('The project\'s slug, from list-projects. May be left out when only one project is in reach.');
    }
}
