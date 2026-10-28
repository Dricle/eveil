<?php

namespace App\Services\Meetings;

use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\DataPart;

/**
 * An invite attached the way a calendar client attaches one:
 * `text/calendar; method=REQUEST`. The `method` parameter is what makes Gmail
 * and Outlook show an invitation with its answer buttons rather than a file
 * to download, and `DataPart` has no way to set it.
 */
class CalendarPart extends DataPart
{
    public function __construct(string $ics)
    {
        parent::__construct($ics, 'invite.ics', 'text/calendar');
    }

    public function getPreparedHeaders(): Headers
    {
        $headers = parent::getPreparedHeaders();

        $headers->setHeaderParameter('Content-Type', 'charset', 'UTF-8');
        $headers->setHeaderParameter('Content-Type', 'method', 'REQUEST');

        return $headers;
    }
}
