/**
 * One drafted or self-reported-posted Reddit reply, as the approval queue
 * sees it. No account, no urn: publishing is manual, since Reddit
 * currently blocks new OAuth app registration.
 */
export type RedditReply = {
    id: number
    subreddit: string | null
    thread_permalink: string
    thread_title: string | null
    source: 'subreddit_scan' | 'seo_thread'
    /** Which buyer-intent query surfaced it, seo_thread only. */
    search_query: string | null
    angle: 'value_comment' | 'soft_mention' | 'dm_invite' | 'user_written'
    /** What grounds this draft, shown beside the body. */
    evidence: string
    body: string
    status: 'draft' | 'published' | 'rejected'
    /** Set on reject, optional - shown beside a rejected reply's body. */
    rejection_reason: string | null
    published_at: string | null
    /** Pasted back by the user after posting on reddit.com themselves. */
    comment_permalink: string | null
    score: number
    stats_checked_at: string | null
    /** Set by "mark as proven" or the score poll crossing the threshold. */
    promoted_at: string | null
    created_at: string | null
}
