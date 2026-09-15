<?php

namespace App\Enums;

enum LinkedinPostStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Published = 'published';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
