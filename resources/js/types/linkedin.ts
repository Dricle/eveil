/**
 * A connected LinkedIn member profile, as the settings screen sees it. No
 * tokens: they are write-only from the UI's point of view.
 */
export type LinkedinAccount = {
    id: number
    display_name: string
    member_urn: string
    status: 'active' | 'expired' | 'error'
    last_error: string | null
    /** Whether the SEPARATE Community Management app connection exists - see docs/self-hosted. */
    has_stats_access: boolean
    /** The projects granted this account, as ids, because it is a checkbox list. */
    projects: number[]
}

/**
 * One drafted or published LinkedIn post, as the approval queue sees it.
 */
export type LinkedinPost = {
    id: number
    source_type: 'knowledge_base' | 'client_won' | 'news' | 'manual'
    /** Only set on a client_won sibling: which of the pair this one is. */
    variant: 'named' | 'anonymized' | null
    /** What grounds this draft, shown beside the body. */
    evidence: string
    body: string
    status: 'draft' | 'published' | 'rejected'
    /** Set on reject, optional - shown beside a rejected post's body. */
    rejection_reason: string | null
    urn: string | null
    published_at: string | null
    /** A failed publish attempt, on a post that stays `draft` - never its own status. */
    last_error: string | null
    /** Set by "mark as successful" or the performance poll crossing the threshold. */
    promoted_at: string | null
    created_at: string | null
}
