export type CompanyEvaluation = {
    profile: string | null
    fit_score: number
    fit_reason: string
}

export type Company = {
    id: number
    name: string
    domain: string
    website: string | null
    industry: string | null
    size: string | null
    location: string | null
    language: string | null
    source: string
    source_url: string | null
    rejected: boolean
    contacts_status: 'queued' | 'done' | 'failed' | null
    contacts_count: number
    discovered_at: string
    /** The best any profile thought of it — what the list sorts on. */
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
    status: string
    discovered_at: string
    company: { id: number, name: string, domain: string, location: string | null } | null
}
