import type { OutreachStatus } from '@/lib/status'

/** What the app is doing for this project right now. */
export type Activity = {
    searching: boolean
    runs: number
    candidates: number
    qualified: number
    contact_searches: number
}

export type CompanyEvaluation = {
    profile: string | null
    fit_score: number
    fit_reason: string
}

export type Company = {
    id: number
    name: string
    domain: string | null
    website: string | null
    industry: string | null
    size: string | null
    location: string | null
    language: string | null
    source: string
    source_url: string | null
    status: OutreachStatus
    /** True for the five statuses that take the company out of outreach. */
    excluded: boolean
    contacts_status: 'queued' | 'done' | 'failed' | null
    contacts_count: number
    discovered_at: string
    /** The best any profile thought of it, which is what the list sorts on. */
    fit_score: number | null
    evaluations: CompanyEvaluation[]
}

/**
 * A paginated list keeps Laravel's envelope rather than being unwrapped: the
 * page needs the meta to draw the pager.
 */
export type Paginated<T> = {
    data: T[]
    meta: {
        current_page: number
        last_page: number
        per_page: number
        total: number
    }
}

export type Contact = {
    id: number
    name: string | null
    title: string | null
    email: string | null
    email_status: 'valid' | 'risky' | 'unknown' | 'invalid' | null
    email_source: 'scraped' | 'inferred' | 'provided' | 'imported' | null
    linkedin_url: string | null
    language: string | null
    source_url: string | null
    status: OutreachStatus
    discovered_at: string
    company: { id: number, name: string, domain: string | null, location: string | null } | null
}

/** One person's whole history: the drill-down behind a row in the contacts list. */
export type ContactSheet = Contact & {
    email_verified_at: string | null
    last_contacted_at: string | null
    won_at: string | null
    source: string
    company_detail: {
        id: number
        name: string
        domain: string | null
        website: string | null
        industry: string | null
        size: string | null
        location: string | null
        status: OutreachStatus
        evaluations: { profile: string | null, fit_score: number, fit_reason: string }[]
    } | null
    campaigns: {
        id: number
        campaign: string
        status: string
        pause_reason: string | null
        step: number
        next_action_at: string | null
        mailbox: string | null
    }[]
    messages: {
        id: number
        direction: 'outbound' | 'inbound'
        subject: string
        body: string
        status: string | null
        classification: keyof typeof import('./inbox').CLASSIFICATIONS | null
        at: string | null
    }[]
}

/** One company with everything found about it, and the people found at it. */
export type CompanySheet = Company & {
    facts: Record<string, unknown> | null
    contacts_searched_at: string | null
    /** True while a contact search is still reading this company's site. */
    searching: boolean
    contacts: Contact[]
}
