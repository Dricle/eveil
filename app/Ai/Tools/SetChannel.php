<?php

namespace App\Ai\Tools;

use App\Enums\Channel;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Switches one channel on or off, the same switch as Settings → Project →
 * Channels. No approval, same as the switch: nothing runs until the
 * channel's own schedule comes round, and switching back is one more call.
 */
class SetChannel implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Switches one of the project's channels on or off: email (off pauses
        the automatic lead search and nothing else), reddit, linkedin, x,
        bluesky, instagram, articles, ads (off pauses every running campaign
        at once). Turning one on starts it weekly. Only
        call it when the user asked for that channel to start or stop.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $channel = Channel::tryFrom($request->string('channel')->value());

        if ($channel === null) {
            return 'No such channel. Use one of: '.implode(', ', array_column(Channel::cases(), 'value')).'.';
        }

        $channel->setFor($this->project, $request->boolean('enabled'));

        return $channel->label().($request->boolean('enabled') ? ' is on.' : ' is off.');
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channel' => $schema->string()->enum(array_column(Channel::cases(), 'value'))->required(),
            'enabled' => $schema->boolean()->required(),
        ];
    }
}
