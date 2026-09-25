import type { SocialPlatform } from '@/types'

export const PLATFORM_LABEL: Record<SocialPlatform, string> = {
    x: 'X',
    bluesky: 'Bluesky'
}

export const PLATFORM_LIMIT: Record<SocialPlatform, number> = {
    x: 280,
    bluesky: 300
}

/**
 * The length each network itself counts. X counts characters with any URL
 * as 23; Bluesky counts graphemes, so an emoji is one.
 * ponytail: X's real weighting also counts CJK characters double, add it
 * if someone writes in those scripts.
 */
export function postLength (platform: SocialPlatform, body: string): number {
    if (platform === 'x') {
        return [...body.replace(/https?:\/\/\S+/g, 'x'.repeat(23))].length
    }

    return [...new Intl.Segmenter().segment(body)].length
}
