<?php

namespace App\Ai\Tools;

use App\Models\Message;
use App\Services\Outreach\ReplyOutcomes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * "Not now." Worth more than the clean exit it would otherwise get: the answer
 * was about timing, and a sequence that comes back when they said to is the
 * cheapest lead in the pipeline.
 */
class RescheduleFollowUp implements Tool
{
    public function __construct(private Message $reply) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Use this when the answer is about timing rather than interest: "ask me
        again next year", "we are mid-migration until the spring", "budget is
        decided in September".

        Give `months` from what they said. When they name a season or a month,
        count from now to it; when they only say "later", use 6.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $months = max(1, min(24, (int) ($request['months'] ?? 6)));

        app(ReplyOutcomes::class)->reschedule($this->reply, $months);

        return "The sequence will pick this person up again in {$months} month(s).";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'months' => $schema->integer()->min(1)->max(24)
                ->description('How many months to wait before the next mail.')
                ->required(),
            'summary' => $schema->string()
                ->description('One sentence on what they asked to postpone, and until when.')
                ->required(),
        ];
    }
}
