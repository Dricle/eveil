import type { OutreachStatus } from '@/lib/status'
import type { CampaignStatus } from '@/types/campaign'

/**
 * What the agent decided about a reply. The label is what the inbox shows; the
 * value is what the tool wrote when it acted.
 */
export const CLASSIFICATIONS = {
    interested: { label: 'Interested', color: 'success' as const, help: 'Plainly positive. This is the number the product is judged on.' },
    needs_human: { label: 'Needs you', color: 'primary' as const, help: 'A question or an ambiguous answer. It needs words only you can write.' },
    wrong_person: { label: 'Wrong person', color: 'warning' as const, help: 'They pointed at somebody else. Nothing was sent to whoever they named.' },
    not_now: { label: 'Later', color: 'neutral' as const, help: 'Postponed. The sequence comes back on its own.' },
    not_interested: { label: 'Not interested', color: 'neutral' as const, help: 'A clean no. The sequence stopped.' },
    unsubscribe: { label: 'Unsubscribed', color: 'error' as const, help: 'Suppressed. This address is never written to again for this project.' },
    auto_reply: { label: 'Auto-reply', color: 'neutral' as const, help: 'A machine answered, so the sequence carried on.' }
}

export type Classification = keyof typeof CLASSIFICATIONS

export type ConversationMessage = {
    id: number
    direction: 'outbound' | 'inbound'
    subject: string
    body: string
    classification: Classification | null
    status: string | null
    at: string | null
}

export type Conversation = {
    id: number
    status: string
    pause_reason: string | null
    campaign: { id: number, name: string }
    lead: {
        id: number
        name: string | null
        email: string | null
        title: string | null
        status: OutreachStatus
        company: string | null
    }
    classification: Classification | null
    needs_attention: boolean
    replied_at: string | null
    /** When the last outbound message was attempted. */
    sent_at: string | null
    /** What became of it. Anything but `sent` means it never reached anybody. */
    delivery: 'queued' | 'sent' | 'failed' | 'bounced' | null
    messages: ConversationMessage[]
}

/** The funnel on a campaign's delivery screen: how far its leads have got. */
export type Pipeline = Partial<Record<'pending' | 'running' | 'paused' | 'completed' | 'failed' | 'stopped', number>>

/**
 * Tokens on self-hosted, credits spent on cloud - never both, and never a
 * conversion between them: a cloud customer must never see a token count.
 * Neither is shown on the dashboard itself; both stay on the wire for
 * whatever reads the same prop next.
 */
export type DashboardStats = {
    companies_found: number
    companies_kept: number
    sent: number
    replies: number
    positive: number
    positive_rate: number | null
    awaiting_human: number
} & ({ tokens_in: number, tokens_out: number } | { credits_spent: number })

export type DashboardCampaign = {
    id: number
    name: string
    status: CampaignStatus
    steps_count: number
    leads_count: number
    sent_count: number
    replies_count: number
}

export type DashboardReply = {
    id: number
    lead: { name: string, company: string | null }
    body: string
    classification: Classification | null
    at: string | null
}

/** The one search currently spending budget, grouped into three real stages. */
export type DashboardDiscoveryRun = {
    id: number
    target_profile_name: string | null
    status: string
    started_at: string | null
    candidates_found: number
    max_companies: number | null
    queries_used: number
    max_queries: number | null
    stages: { label: string, done: number, total: number, state: 'waiting' | 'running' | 'done' }[]
}
