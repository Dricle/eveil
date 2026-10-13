<?php

namespace App\Mcp\Tools;

use App\Http\Resources\CampaignLeadResource;
use App\Mcp\Tools\Concerns\ActsOnProject;
use App\Mcp\Tools\Concerns\PagesResults;
use App\Models\Campaign;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List everyone in one campaign and where they are in it: status, current step, emails sent.')]
class ListCampaignLeads extends Tool
{
    use ActsOnProject, PagesResults;

    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate(['campaign_id' => ['required', 'integer']]);

        $campaign = Campaign::query()->find($request->integer('campaign_id'));

        if ($campaign === null) {
            return Response::error('No campaign with that id in this project.');
        }

        return $this->paginated(
            $campaign->campaignLeads()->with('lead.company')->withCount('sentMessages')->orderBy('id'),
            CampaignLeadResource::class,
            $request,
        );
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $this->projectArgument($schema),
            'campaign_id' => $schema->integer()->description('The campaign id.')->required(),
            'page' => $this->pageArgument($schema),
        ];
    }
}
