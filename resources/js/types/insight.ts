export type InsightKind = 'article' | 'acquisition' | 'feature' | 'competitor_mention'

export type CompetitorSignal = 'complaint' | 'comparison' | 'switching' | 'praise'

/**
 * One item of the Insights feed: something Eveil noticed while reading.
 */
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
    }
    read_at: string | null
    created_at: string | null
}
