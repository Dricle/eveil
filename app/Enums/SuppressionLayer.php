<?php

namespace App\Enums;

enum SuppressionLayer: string
{
    case OptOut = 'opt_out';
    case Bounce = 'bounce';
    case Toxic = 'toxic';
}
