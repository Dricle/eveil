/**
 * One SEO article, as the SEO page sees it. Published by hand: the user
 * pastes it into their CMS and gives the URL back.
 */
export type Article = {
    id: number
    source_type: 'feature' | 'competitor' | 'reddit_thread' | 'client_won' | 'news' | 'agent_choice' | 'manual'
    /** A thread permalink, a competitor name or a company id, depending on the source. */
    source_ref: string | null
    /** Why this article, shown beside it. */
    evidence: string
    title: string
    meta_description: string | null
    /** Markdown. */
    body: string
    language: string | null
    status: 'draft' | 'published' | 'rejected'
    rejection_reason: string | null
    published_url: string | null
    published_at: string | null
    created_at: string | null
}

/**
 * A discussion the Reddit scan noted as worth an article, not written yet.
 */
export type ArticleIdea = {
    id: number
    source: 'reddit'
    /** The thread permalink. */
    source_ref: string
    title: string
    /** What the article would answer or argue. */
    angle: string
    created_at: string | null
}
