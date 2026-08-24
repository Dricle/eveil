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
    language?: string
    confidence?: number
}

/**
 * Something the website never said. `key` is the identity, so a later reading
 * that rephrases the question does not ask it a second time.
 */
export type OpenQuestion = {
    key: string
    question: string
    answer: string | null
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
        access_angle?: string
        partnership_angle?: string
        technologies?: string[]
        trigger_signals?: string[]
        search_queries?: string[]
        confidence?: number
    }
}

/** What the project settings screens get, on top of what the switcher needs. */
export type ProjectDetail = Project & {
    edited_by_user: boolean
    prompt_instructions: string | null
    /** How much of the run happens without being asked. */
    autonomy_level: 'supervised' | 'semi_auto' | 'autonomous'
    /** Pauses continuous discovery for the rest of the day once reached. Null is uncapped. */
    daily_lead_limit: number | null
    /** Stops continuous discovery permanently once reached. Null is uncapped. */
    lead_limit: number | null
    knowledge_base: KnowledgeBase | null
    open_questions: OpenQuestion[]
    last_analysis: Analysis | null
}
