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
    finished_at: string | null
}

/** What the project settings screens get, on top of what the switcher needs. */
export type ProjectDetail = Project & {
    edited_by_user: boolean
    knowledge_base: KnowledgeBase | null
    last_analysis: Analysis | null
}
