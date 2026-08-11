<?php

namespace App\Enums;

enum AutonomyLevel: string
{
    case Supervised = 'supervised';
    case SemiAuto = 'semi_auto';
    case Autonomous = 'autonomous';
}
