export type OrganizationRole = 'owner' | 'admin' | 'member'

/**
 * One row of the members screen. `projects` only means something for
 * `member`: Owner and Admin bypass the grant and reach every project, so the
 * picker only appears for a row that is actually restricted by it.
 */
export type Member = {
    id: number
    name: string
    email: string
    role: OrganizationRole
    is_you: boolean
    projects: number[]
}
