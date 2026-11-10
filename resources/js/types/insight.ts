export type InsightKind = 'article' | 'acquisition' | 'feature' | 'competitor_mention'

export type CompetitorSignal = 'complaint' | 'comparison' | 'switching' | 'praise'

/**
 * One item of the Insights feed: something Eveil noticed while reading.
 */
export type ArticleThread = {
    title: string
    url: string
    subreddit?: string | null
    /** A missing feature's thread: what people there say, in Eveil's words. */
    evidence?: string
}

export type Insight = {
    id: number
    kind: InsightKind
    status: 'open' | 'used' | 'dismissed'
    source: string
    source_ref: string
    title: string
    /** The summary: an article's angle, a recommendation's evidence, what users think of a competitor. */
    angle: string
    url: string | null
    details: {
        impact?: 'high' | 'medium' | 'low'
        effort?: 'high' | 'medium' | 'low'
        competitor?: string
        signal?: CompetitorSignal
        subreddit?: string | null
        /** A feature idea's thread, whose own title is the idea. */
        thread_title?: string | null
        /** An article topic's or a missing feature's threads, every one that asked for it. */
        threads?: ArticleThread[]
    }
    /** How many threads asked for an article topic or a missing feature. 1 for everything else. */
    mentions: number
    read_at: string | null
    created_at: string | null
}
