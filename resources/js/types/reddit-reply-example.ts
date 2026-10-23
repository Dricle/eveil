export type RedditReplyExampleRow = {
    id: number
    body: string
    source: 'manual' | 'promoted' | 'harvested'
    added_by: string | null
    // Harvested rows only: the comment somebody else wrote, and why it was kept.
    comment_permalink: string | null
    comment_author: string | null
    subreddit: string | null
    score: number | null
    harvest_reason: string | null
    created_at: string | null
}
