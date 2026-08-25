export type CreditTransactionRow = {
    type: string
    credits: number
    agent: string | null
    created_at: string | null
}

export type ProjectCreditRow = {
    id: number
    name: string
    credits: number
}
