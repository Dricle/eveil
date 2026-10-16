<?php

namespace App\Enums;

/**
 * A decision's life. `Blocked` is the guardrails refusing it (over budget, too
 * steep a raise): the agent reads why at its next review. `Failed` is the
 * network refusing it.
 */
enum AdDecisionStatus: string
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Applied = 'applied';
    case Rejected = 'rejected';
    case Blocked = 'blocked';
    case Failed = 'failed';
}
