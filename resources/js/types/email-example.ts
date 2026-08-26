export type EmailExampleRow = {
    id: number
    subject: string
    body: string
    source: 'manual' | 'campaign'
    added_by: string | null
    created_at: string | null
}
