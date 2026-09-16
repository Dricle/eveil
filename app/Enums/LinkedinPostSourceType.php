<?php

namespace App\Enums;

/**
 * What grounded a draft. Shown beside the body in the approval queue, same
 * "evidence or nothing" discipline as `CompanyTargetEvaluation`.
 */
enum LinkedinPostSourceType: string
{
    case KnowledgeBase = 'knowledge_base';
    case ClientWon = 'client_won';
    case News = 'news';
    case Manual = 'manual';
}
