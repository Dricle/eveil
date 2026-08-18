export type Project = {
    id: number
    name: string
    url: string
    analyzed: boolean
}

export type KnowledgeBase = {
    what_it_does: string
    who_it_is_for: string
    value_proposition: string
    positioning: string
    pricing_model: string
    key_features: string[]
    competitors: string[]
    proof_points: string[]
    gaps: string[]
    language?: string
    confidence?: number
}

export type Analysis = {
    id: number
    status: string
    error: string | null
    failures: { url: string, reason: string }[]
    pages_read: number
    pages_planned: number
    running: boolean
    finished_at: string | null
}

/** Who the search goes after. Everything but the name lives in `criteria`. */
export type TargetProfile = {
    id: number
    name: string
    type: 'customer' | 'partner'
    source: 'agent' | 'human'
    is_active: boolean
    criteria: {
        rationale?: string
        company_size?: string
        estimated_market_size?: string
        sectors?: string[]
        geography?: string[]
        job_titles?: string[]
        technologies?: string[]
        trigger_signals?: string[]
        search_queries?: string[]
        confidence?: number
    }
}

/** What the project settings screens get, on top of what the switcher needs. */
export type ProjectDetail = Project & {
    edited_by_user: boolean
    knowledge_base: KnowledgeBase | null
    last_analysis: Analysis | null
}
