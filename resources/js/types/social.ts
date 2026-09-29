export type SocialPlatform = 'linkedin' | 'x' | 'bluesky'

/**
 * A connected LinkedIn or Bluesky account, as the settings screens see it.
 * No credentials: they are write-only from the UI's point of view.
 */
export type SocialAccount = {
    id: number
    platform: SocialPlatform
    /** Bluesky only: LinkedIn has no handle. */
    handle: string | null
    display_name: string
    status: 'active' | 'expired' | 'error'
    last_error: string | null
    /** LinkedIn only: whether the SEPARATE performance-polling app is connected. */
    has_stats_access: boolean
    /** The projects granted this account, as ids, because it is a checkbox list. */
    projects?: number[]
}

/**
 * One drafted or published post, as a network's queue sees it.
 */
export type SocialPost = {
    id: number
    platform: SocialPlatform
    source_type: 'knowledge_base' | 'client_won' | 'news' | 'article' | 'manual'
    /** Only set on a client_won sibling: which of the pair this one is. */
    variant: 'named' | 'anonymized' | null
    /** What grounds this draft, shown beside the body. */
    evidence: string
    body: string
    status: 'draft' | 'published' | 'rejected'
    rejection_reason: string | null
    /** The account it went out on. Always null on X. */
    social_account: { id: number, handle: string | null, display_name: string } | null
    /** The live post. On X, the URL the user pasted back. */
    url: string | null
    published_at: string | null
    /** A failed publish attempt, on a post that stays `draft` - never its own status. */
    last_error: string | null
    /** Set by "mark as successful" or the stats poll crossing the threshold. */
    promoted_at: string | null
    /** Only read where the network's numbers are: never on X. */
    likes_count: number
    created_at: string | null
}

/**
 * One row of a shared, instance-wide bank of proven posts for one network.
 */
export type SocialPostExampleRow = {
    id: number
    platform: SocialPlatform
    body: string
    source: 'manual' | 'promoted'
    added_by: string | null
    created_at: string | null
}
