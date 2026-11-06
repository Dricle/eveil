<?php

namespace App\Enums;

/**
 * What a Reddit thread naming a competitor says about it, as the Reddit
 * scan's triage reads it.
 */
enum CompetitorSignal: string
{
    case Complaint = 'complaint';
    case Comparison = 'comparison';
    case Switching = 'switching';
    case Praise = 'praise';
}
