<?php

namespace App\Ai\Tools;

use App\Actions\AddLaunchVenue as AddLaunchVenueAction;
use App\Enums\LaunchVenueKind;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The Launch page's "Add a venue": a place the catalog does not have, added
 * to this project's checklist.
 */
class AddLaunchVenue implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Adds a place to post the product that the launch checklist does not list yet: a niche directory, a community, a newsletter. Adding the same address again restores the existing one.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'kind' => ['required', 'in:'.implode(',', array_column(LaunchVenueKind::cases(), 'value'))],
            'is_paid' => ['boolean'],
            'price_note' => ['nullable', 'string', 'max:255'],
            'cooldown_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'rules' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return 'Not added: '.implode(' ', $validator->errors()->all());
        }

        $item = app(AddLaunchVenueAction::class)->handle($this->project, $validator->validated());

        return "Added to the launch checklist as #{$item->id}.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'url' => $schema->string()->description('Where to post, ideally the submit page.')->required(),
            'kind' => $schema->string()->enum(array_column(LaunchVenueKind::cases(), 'value'))->required(),
            'is_paid' => $schema->boolean(),
            'price_note' => $schema->string()->description('What it costs, in a few words.'),
            'cooldown_days' => $schema->integer()->description('Days before it can take another post; leave out if it takes one.'),
            'rules' => $schema->string()->description('What the venue expects: fields, length limits, tone.'),
        ];
    }
}
