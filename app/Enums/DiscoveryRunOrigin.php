<?php

namespace App\Enums;

/**
 * What started a run. `Search` describes the AI planning-and-probing loop, the
 * only thing `ContinueDiscovery`'s cadence and `DiscoveryRun::diagnose()`'s
 * "too narrow / wrong source" vocabulary are about. `Manual` is a batch of
 * links the user already had: it must never block the scheduled cadence for
 * the profile, and never gets an AI-search diagnosis.
 */
enum DiscoveryRunOrigin: string
{
    case Search = 'search';
    case Manual = 'manual';
}
