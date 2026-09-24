import type { RedditReply } from '@/types'

export type RedditThread = {
    permalink: string
    subreddit: string | null
    threadTitle: string | null
    source: 'subreddit_scan' | 'seo_thread'
    searchQuery: string | null
    evidence: string
    replies: RedditReply[]
}

/**
 * Up to 3 angle drafts share one thread_permalink, so the queue shows a
 * thread with its angles rather than one row per draft. Filter by status
 * BEFORE grouping: a thread can otherwise mix a draft, a posted and a
 * rejected angle.
 */
export function groupThreads (replies: RedditReply[]): RedditThread[] {
    const byPermalink = new Map<string, RedditThread>()

    for (const reply of replies) {
        let thread = byPermalink.get(reply.thread_permalink)

        if (!thread) {
            thread = {
                permalink: reply.thread_permalink,
                subreddit: reply.subreddit,
                threadTitle: reply.thread_title,
                source: reply.source,
                searchQuery: reply.search_query,
                evidence: reply.evidence,
                replies: []
            }
            byPermalink.set(reply.thread_permalink, thread)
        }

        thread.replies.push(reply)
    }

    return Array.from(byPermalink.values())
}
