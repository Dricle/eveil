<?php

namespace App\Enums;

enum EmailSource: string
{
    case Scraped = 'scraped';
    case Inferred = 'inferred';
    case Provided = 'provided';
    case Imported = 'imported';
}
