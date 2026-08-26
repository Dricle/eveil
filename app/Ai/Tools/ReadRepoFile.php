<?php

namespace App\Ai\Tools;

use App\Services\RepoReader;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The other half of roaming: `ListRepoPaths` is free, this one is a real
 * GitHub request per call, so the model spends it on files it actually
 * decided matter. Same byte cap and NUL/UTF-8 cleanup as the priority-file
 * read `RepoReader::read()` already does, via the same private fetch.
 */
class ReadRepoFile implements Tool
{
    public function __construct(
        private RepoReader $reader,
        private string $owner,
        private string $repo,
        private string $branch,
        private ?string $githubToken = null,
    ) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Read one file's full text content by its exact path. List a directory
        first if you are not sure of the path. Returns an explanation instead
        of the content when the file is missing, binary, or too large to read.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $path = trim((string) ($request['path'] ?? ''), '/');

        $text = $this->reader->file($this->owner, $this->repo, $this->branch, $path, $this->githubToken);

        return $text ?? "Could not read \"{$path}\": missing, binary, or too large.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('Full path to the file, e.g. "src/index.ts", exactly as the directory listing showed it.')
                ->required(),
        ];
    }
}
