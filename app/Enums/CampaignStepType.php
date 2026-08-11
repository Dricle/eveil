<?php

namespace App\Enums;

enum CampaignStepType: string
{
    case Email = 'email';
    case Wait = 'wait';
}
