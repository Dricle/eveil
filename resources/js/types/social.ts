export type SocialPlatform = 'linkedin' | 'x' | 'bluesky' | 'instagram'

/**
 * A connected LinkedIn, Bluesky or Instagram account, as the settings screens see it.
 * No credentials: they are write-only from the UI's point of view.
 */
export type SocialAccount = {
    id: number
    platform: SocialPlatform
    /** Bluesky and Instagram: LinkedIn has no handle. */
    handle: string | null
    display_name: string
    status: 'active' | 'expired' | 'error'
    last_error: string | null
    /** LinkedIn only: whether the SEPARATE performance-polling app is connected. */
    has_stats_access: boolean
    /** Instagram only: connected through the organization's own Meta app. */
    via_meta_app?: boolean
    /** Instagram only: connected through Composio. */
    via_composio?: boolean
    /** Instagram only: which way in publishes right now; the Meta app wins while its token lives. */
    publishes_through?: 'meta' | 'composio' | null
    meta_token_expires_at?: string | null
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
    /** Instagram only: the picked image, the one that gets published. */
    image_url: string | null
    /** Instagram only: every image drawn for this post, to compare and pick from. */
    images?: { id: number, url: string, selected: boolean }[]
    /** Instagram only: an image queued or being drawn for this post right now. */
    drawing_image: boolean
    /** Width and height the video is rendered at, in this network's shape. */
    video_size: [number, number]
    /** What the writer said the video should show, when it asked for one. */
    video_brief: string | null
    /** The rendered video, published instead of the image. */
    video_url: string | null
    /** The live preview of the current script, for the editor. */
    video_preview_url: string | null
    /** Every version so far: what the user asked, at which second, and what changed. */
    video_revisions: { feedback: string | null, at: number | null, summary: string, created_at: string }[]
    /** A video queued or being made for this post right now. */
    making_video: boolean
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
