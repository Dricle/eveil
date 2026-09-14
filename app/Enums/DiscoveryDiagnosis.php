<?php

namespace App\Enums;

enum DiscoveryDiagnosis: string
{
    case TooNarrow = 'too_narrow';
    case WrongSource = 'wrong_source';
    case BadTargetProfile = 'bad_target_profile';
    case NoContacts = 'no_contacts';
    // Candidates were found but none qualified, on a profile that HAS
    // qualified companies before - a proven target having a quiet run
    // (a blocked source, a thinner day), never the wrong-target verdict.
    // Never blocks `ContinueDiscovery` from trying again: see
    // `DiscoveryRun::mayWiden()`.
    case Saturated = 'saturated';
}
