<?php

namespace App\Enums;

/**
 * What an article was built from, shown beside the draft. Every one of them
 * traces to something Eveil already found, except `AgentChoice`, the
 * writer's own pick when nothing else was strong enough, and `Manual`, a
 * brief the user gave Evie.
 */
enum ArticleSourceType: string
{
    case Feature = 'feature';
    case Competitor = 'competitor';
    case RedditThread = 'reddit_thread';
    case ClientWon = 'client_won';
    case News = 'news';
    case AgentChoice = 'agent_choice';
    case Manual = 'manual';
}
