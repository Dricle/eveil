import type { OutreachStatus } from '@/lib/status'

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

/** The funnel on the dashboard: how far the people in sequences have got. */
export type Pipeline = Partial<Record<'pending' | 'running' | 'paused' | 'completed' | 'failed' | 'stopped', number>>

export type DashboardStats = {
    companies: number
    contacts: number
    active_campaigns: number
    sent: number
    replies: number
    positive: number
    positive_rate: number | null
    awaiting_human: number
    tokens_in: number
    tokens_out: number
}
