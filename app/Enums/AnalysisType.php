<?php

namespace App\Enums;

enum AnalysisType: string
{
    case Website = 'website';
    case Repo = 'repo';
    case RepoDeep = 'repo_deep';
}
