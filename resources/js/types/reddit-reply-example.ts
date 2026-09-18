export type RedditReplyExampleRow = {
    id: number
    body: string
    source: 'manual' | 'promoted'
    added_by: string | null
    created_at: string | null
}
