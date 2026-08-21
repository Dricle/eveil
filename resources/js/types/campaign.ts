/** What a sequence is doing. Only the first three are ever set by hand. */
export type CampaignStatus = 'draft' | 'active' | 'paused' | 'completed' | 'archived'

/**
 * Why the next mail has not left yet. Every figure here comes from the rule the
 * scheduler actually applies, never from a copy of it.
 */
export type SendingState = {
    next_action_at: string | null
    window_open: boolean
    window: { start: number, end: number }
    mailboxes: {
        id: number
        name: string
        email: string
        status: string
        sent_today: number
        allowance: number
        remaining: number
        ready_at: string | null
    }[]
}

/** One person's place in one sequence. */
export type CampaignLead = {
    id: number
    name: string | null
    email: string | null
    company: string | null
    status: string
    /** Position of the last step done. Zero means nothing has been sent yet. */
    last_step: number
    next_action_at: string | null
    pause_reason: string | null
    sent?: number
}
