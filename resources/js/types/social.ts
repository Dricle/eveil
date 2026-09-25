export type SocialPlatform = 'x' | 'bluesky'

/**
 * A connected Bluesky account, as the settings screen sees it. No app
 * password: it is write-only from the UI's point of view.
 */
export type SocialAccount = {
    id: number
    platform: SocialPlatform
    handle: string
    display_name: string
    status: 'active' | 'error'
    last_error: string | null
    /** The projects granted this account, as ids, because it is a checkbox list. */
    projects?: number[]
}

/**
 * One drafted or published X or Bluesky post, as the queue sees it.
 */
export type SocialPost = {
    id: number
    platform: SocialPlatform
    source_type: 'knowledge_base' | 'client_won' | 'news' | 'article' | 'manual'
    /** What grounds this draft, shown beside the body. */
    evidence: string
    body: string
    status: 'draft' | 'published' | 'rejected'
    rejection_reason: string | null
    /** The Bluesky account it went out on. Always null on X. */
    social_account: { id: number, handle: string } | null
    /** The live post. On X, the URL the user pasted back. */
    url: string | null
    published_at: string | null
    /** A failed Bluesky publish, on a post that stays `draft`. */
    last_error: string | null
    promoted_at: string | null
    /** Bluesky only: X numbers are never read. */
    likes_count: number
    created_at: string | null
}

/**
 * One row of a shared, instance-wide bank of proven X or Bluesky posts.
 */
export type SocialPostExampleRow = {
    id: number
    platform: SocialPlatform
    body: string
    source: 'manual' | 'promoted'
    added_by: string | null
    created_at: string | null
}
