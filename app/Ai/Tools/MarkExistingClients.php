<?php

namespace App\Ai\Tools;

use App\Actions\MarkKnownClients;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The "Existing clients" box on Companies: emails and websites marked
 * `client`, so no campaign ever cold-mails them. No approval: it only ever
 * takes people OUT of outreach, which is the safe direction.
 */
class MarkExistingClients implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Marks people or companies the user already works with as existing clients, so Eveil never writes to them. Each entry is an email address (one person) or a website (the whole company). Unknown ones are created already marked, so a later search cannot add them as new.';
    }

    public function handle(Request $request): Stringable|string
    {
        $entries = array_values(array_filter((array) $request['entries'], 'is_string'));

        if ($entries === []) {
            return 'Give at least one email address or website.';
        }

        $report = app(MarkKnownClients::class)->handle($this->project, array_slice($entries, 0, 500));

        return "Marked {$report['companies']} companies and {$report['leads']} people as existing clients.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'entries' => $schema->array()
                ->items($schema->string())
                ->description('Email addresses and websites, up to 500.')
                ->required(),
        ];
    }
}
