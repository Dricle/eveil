<?php

namespace App\Enums;

enum DiscoveryDiagnosis: string
{
    case TooNarrow = 'too_narrow';
    case WrongSource = 'wrong_source';
    case BadTargetProfile = 'bad_target_profile';
    case NoContacts = 'no_contacts';
}
