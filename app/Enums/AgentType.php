<?php

namespace App\Enums;

enum AgentType: string
{
    case Planner = 'planner';
    case Extractor = 'extractor';
    case Qualifier = 'qualifier';
    case Writer = 'writer';
    case Classifier = 'classifier';
}
