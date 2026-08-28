<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How the explorer agent roams: the full path list is fetched once by
 * `RepoReader::paths()` and handed here, so navigating deeper never costs
 * another GitHub call - only `ReadRepoFile` does. A directory is listed
 * with a trailing slash, a file without, the same convention `ls` uses.
 */
class ListRepoPaths implements Tool
{
    /**
     * @param  Collection<int, string>  $paths
     */
    public function __construct(private Collection $paths) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        List the files and folders directly under one directory of the repo.
        Pass an empty string to list the repo root. Folders end with "/" -
        call this tool again with a folder's own path to look inside it.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $prefix = trim((string) ($request['directory'] ?? ''), '/');

        $children = $this->paths->filter(fn (string $path): bool => $prefix === ''
            ? true
            : str_starts_with($path, $prefix.'/'));

        if ($children->isEmpty()) {
            return "Nothing found under \"{$prefix}\".";
        }

        $entries = $children
            ->map(fn (string $path): string => $prefix === '' ? $path : mb_substr($path, mb_strlen($prefix) + 1))
            ->mapWithKeys(function (string $relative): array {
                $segments = explode('/', $relative, 2);

                return [$segments[0] => count($segments) > 1];
            })
            ->map(fn (bool $isDirectory, string $name): string => $isDirectory ? "{$name}/" : $name)
            ->unique()
            ->sort()
            ->values();

        return $entries->implode("\n");
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'directory' => $schema->string()
                ->description('Path of the directory to list, relative to the repo root. Empty string for the root.')
                ->required(),
        ];
    }
}
