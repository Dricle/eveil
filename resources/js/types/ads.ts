export type AdStatus = 'draft' | 'active' | 'paused' | 'removed'

export type AdAccount = {
    id: number
    platform: string
    platform_label: string
    name: string
    external_id: string
    currency: string
    /** Currency units, not micros. Zero means Eveil spends nothing here. */
    monthly_budget: number
    spent_this_month: number
    active_daily_budget: number
    has_conversion_tracking: boolean
    status: 'active' | 'error'
    last_error: string | null
    metrics_synced_at: string | null
}

export type Ad = {
    id: number
    status: AdStatus
    creative_id: number
    angle: string
    headlines: string[]
    descriptions: string[]
    final_url: string
    impressions: number
    clicks: number
    cost: number
    conversions: number
    ctr: number | null
}

export type AdCampaign = {
    id: number
    ad_account_id: number
    name: string
    hypothesis: string
    targeting: { keywords?: string[], negative_keywords?: string[], countries?: string[], languages?: string[] }
    daily_budget: number
    currency: string
    platform_label: string
    status: AdStatus
    launched_at: string | null
    ads: Ad[]
}

export type AdDecision = {
    id: number
    type: 'launch_campaign' | 'add_ad' | 'pause_ad' | 'pause_campaign' | 'set_daily_budget'
    type_label: string
    status: 'proposed' | 'approved' | 'applied' | 'rejected' | 'blocked' | 'failed'
    campaign_id: number | null
    ad_id: number | null
    daily_budget: number | null
    reasoning: string
    outcome: string | null
    by_agent: boolean
    decided_by: string | null
    created_at: string
}
