<?php

namespace App\Enums;

enum DiscoveryDiagnosis: string
{
    case TooNarrow = 'too_narrow';
    case WrongSource = 'wrong_source';
    case BadIcp = 'bad_icp';
    case NoContacts = 'no_contacts';
}
