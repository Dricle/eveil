<?php

namespace App\Enums;

/**
 * Which discovery mechanism found the thread - `App\Services\Reddit\OpportunityScanner`
 * or `App\Services\Reddit\SeoThreadFinder`. Both feed the same triage/writer/
 * approval pipeline; this is read by the writer (a live discussion is
 * answered differently from an evergreen "best X" thread) and by the UI
 * (labelling a card "found in r/x" vs "ranks on Google for '<query>'").
 */
enum RedditReplySource: string
{
    case SubredditScan = 'subreddit_scan';
    case SeoThread = 'seo_thread';
}
