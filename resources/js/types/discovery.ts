/** One node of a run's graph: what it did, what it cost, how it failed. */
export type DiscoveryTask = {
    id: number
    kind: 'plan' | 'probe' | 'harvest' | 'qualify'
    status: 'pending' | 'running' | 'succeeded' | 'failed' | 'skipped'
    subject: string
    result: Record<string, unknown> & { failures?: string[] }
    error: string | null
    attempts: number
    tokens: number | null
    duration_ms: number | null
}

export type DiscoveryRun = {
    id: number
    status: string
    running: boolean
    diagnosis: 'too_narrow' | 'wrong_source' | 'bad_target_profile' | 'no_contacts' | null
    error: string | null
    profile: string | null
    plan: string | null
    budget: Record<string, number>
    spent: {
        queries: number
        candidates: number
        pages: number
        qualified: number
    }
    started_at: string | null
    finished_at: string | null
    tasks?: DiscoveryTask[]
}
