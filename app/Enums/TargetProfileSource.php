<?php

namespace App\Enums;

enum TargetProfileSource: string
{
    case Agent = 'agent';
    case Human = 'human';
}
