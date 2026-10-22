<?php

namespace App\Ai\Tools;

use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;

/**
 * Every project tool, whoever calls it: Evie in chat, and assistants over MCP
 * (`EveilServer`). A tool listed here is public on `/mcp` the moment it ships,
 * so a chat-only one stays in its agent instead (`ProposeSuggestedReplies`).
 */
class Toolbelt
{
    /**
     * In the order Evie is given them.
     *
     * @var list<class-string<Tool>>
     */
    public const TOOLS = [
        ListTargetProfiles::class,
        GetTargetProfile::class,
        CreateTargetProfile::class,
        UpdateTargetProfile::class,
        DeleteTargetProfile::class,
        ListCompanies::class,
        GetCompany::class,
        GetContact::class,
        MarkExistingClients::class,
        AddLeadNote::class,
        DeleteLeadNote::class,
        AddCompanyNote::class,
        DeleteCompanyNote::class,
        DeleteAllCompanies::class,
        DeleteAllLeads::class,
        GetKnowledgeBase::class,
        UpdateKnowledgeBase::class,
        SetChannel::class,
        ProposeRecommendation::class,
        FindFeatureGaps::class,
        UpdateRecommendation::class,
        RefreshAcquisitionIdeas::class,
        ListCampaigns::class,
        GetCampaign::class,
        GetDiscoveryRunStatus::class,
        StartDiscovery::class,
        FindNewTargetProfiles::class,
        CreateSequence::class,
        UpdateSequence::class,
        DraftSocialPost::class,
        ListSocialPosts::class,
        UpdateSocialPost::class,
        RequestSocialPostImage::class,
        SelectSocialPostImage::class,
        ListArticles::class,
        GetArticle::class,
        DraftArticle::class,
        UpdateArticle::class,
        ListArticleIdeas::class,
        DismissArticleIdea::class,
        GetRedditReply::class,
        UpdateRedditReply::class,
    ];

    /**
     * `TOOLS` by the name the MCP server calls them, kebab-cased
     * (`list-target-profiles`).
     *
     * @return array<string, class-string<Tool>>
     */
    public static function byName(): array
    {
        return array_combine(array_map(fn (string $tool): string => Str::kebab(class_basename($tool)), self::TOOLS), self::TOOLS);
    }
}
