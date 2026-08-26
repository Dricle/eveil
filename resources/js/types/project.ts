export type Project = {
    id: number
    name: string
    url: string
    analyzed: boolean
    organization_id: number
    organization_name: string
}

/** The switcher's "other organizations" list, and the current one's label. */
export type Organization = {
    id: number
    name: string
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
    repositories?: RepoFindings[]
    recommendations?: Recommendation[]
}

/** What `RepoAnalyst` found in one linked repo, folded into the knowledge base. */
export type RepoFindings = {
    code_repository_id: number
    name: string
    capabilities: string[]
    hidden_features: string[]
    tech_stack: string[]
    confidence: number
}

/**
 * An acquisition lever the product is missing, grounded in specific
 * evidence (ADR-032, minus its state machine: this list is replaced
 * wholesale on each re-analysis, nothing is dismissed or done yet).
 */
export type Recommendation = {
    key: string
    idea: string
    evidence: string
    impact: 'high' | 'medium' | 'low'
    effort: 'high' | 'medium' | 'low'
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
    type: 'website' | 'repo' | 'repo_deep'
    status: string
    error: string | null
    failures: { url: string, reason: string }[]
    pages_read: number
    pages_planned: number
    running: boolean
    finished_at: string | null
    /** Null until the analysis succeeds. Shape depends on `type`. */
    summary: Record<string, unknown> | null
    /** What was actually read: pages for a website, files for a repo. */
    files: { url: string, title: string, chars: number }[]
}

/** What `RepoAnalyst` and `RepoExplorer` both return, `files_read` deep-only. */
export type RepoAnalysisSummary = {
    capabilities: string[]
    hidden_features: string[]
    tech_stack: string[]
    confidence: number
    files_read?: string[]
}

export type CodeRepositoryRow = {
    id: number
    name: string
    url: string
    provider: string | null
    last_analysis: Analysis | null
}

/** Who the search goes after. Everything but the name lives in `criteria`. */
export type TargetProfile = {
    id: number
    name: string
    type: 'customer' | 'partner'
    source: 'agent' | 'human'
    is_active: boolean
    confidence: number | null
    needs_review: boolean
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
    has_github_token: boolean
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
    code_repositories: CodeRepositoryRow[]
}
