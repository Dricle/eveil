<?php

namespace App\Enums;

/**
 * What a post was built from, shown beside the draft. `Article` shares one
 * of the project's own published SEO articles; `Manual` is a brief the user
 * gave Evie.
 */
enum SocialPostSourceType: string
{
    case KnowledgeBase = 'knowledge_base';
    case ClientWon = 'client_won';
    case News = 'news';
    case Article = 'article';
    case Manual = 'manual';
}
