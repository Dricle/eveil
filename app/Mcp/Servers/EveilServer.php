<?php

namespace App\Mcp\Servers;

use App\Ai\Tools\Toolbelt;
use App\Mcp\Methods\CallProjectTool;
use App\Mcp\Tools\AddContacts;
use App\Mcp\Tools\AiTool;
use App\Mcp\Tools\EnrolCampaignLeads;
use App\Mcp\Tools\ListCampaignLeads;
use App\Mcp\Tools\ListContacts;
use App\Mcp\Tools\ListProjects;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Eveil over MCP, at `/mcp` (`routes/ai.php`). A token reaches an
 * organization's projects, and each call names one: `CallProjectTool` sets it
 * before the tool runs, then the `BelongsToProject` scope does the rest.
 *
 * The whole `Toolbelt` (`AiTool`), so an assistant can do what Evie can,
 * plus what only an integration needs: finding the projects, paging
 * through every contact or campaign member, adding contacts, enrolling.
 */
#[Name('Eveil')]
#[Version('1.0.0')]
#[Instructions('Eveil finds companies that fit a target profile, finds the people to write to, and runs email campaigns, for one or more projects (products or businesses). Call list-projects first: every other tool takes a project slug as `project`. Read a project\'s companies, contacts and campaigns, add contacts, add newly found people to a running campaign, or ask for a social post.')]
class EveilServer extends Server
{
    /**
     * The whole toolbelt in one `tools/list` page: clients that never ask
     * for the next page would otherwise see a third of it.
     */
    public int $defaultPaginationLength = 100;

    public int $maxPaginationLength = 100;

    protected array $tools = [
        ListProjects::class,
        ListContacts::class,
        AddContacts::class,
        ListCampaignLeads::class,
        EnrolCampaignLeads::class,
    ];

    protected function boot(): void
    {
        $this->addMethod('tools/call', CallProjectTool::class);

        foreach (Toolbelt::byName() as $name => $tool) {
            $this->tools[] = new AiTool($name, $tool);
        }
    }
}
