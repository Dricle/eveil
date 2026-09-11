/**
 * One vocabulary for a company and for a person, in the order the work actually
 * goes: found, queued, written to, answered, and then the five ways it ends.
 * Everything from `won` down takes the row out of outreach. The labels say so,
 * because a dropdown that silently stops mails going out is a dropdown nobody
 * trusts.
 *
 * Setting it on either end sets it on the other (`App\Actions\SetOutreachStatus`),
 * which is why there is a single list here rather than one per table.
 *
 * Kept in step with `App\Enums\OutreachStatus` by hand: the server validates
 * against the enum, so a value that drifts here is a rejected request rather
 * than a bad write.
 */
export type OutreachStatus = 'new' | 'queued' | 'contacted' | 'replied' | 'in_discussion' | 'won' | 'lost' | 'client' | 'rejected' | 'suppressed'

export type StatusOption = { label: string, value: OutreachStatus, icon: string, description: string }

// `description` renders under the label in the dropdown (USelect's
// `descriptionKey` default) - the answer to "what does picking this
// actually do", right where the choice is made, so nobody has to guess or
// go find a doc for it.
export const OUTREACH_STATUSES: StatusOption[] = [
    { label: 'New', value: 'new', icon: 'i-lucide-circle-dashed', description: 'Not written to yet. The default for anyone just found.' },
    { label: 'Queued', value: 'queued', icon: 'i-lucide-clock', description: 'Waiting its turn in a running sequence.' },
    { label: 'Contacted', value: 'contacted', icon: 'i-lucide-send', description: 'At least one mail has gone out.' },
    { label: 'Replied', value: 'replied', icon: 'i-lucide-message-square', description: 'They wrote back. Set automatically the moment a reply arrives.' },
    { label: 'In discussion', value: 'in_discussion', icon: 'i-lucide-messages-square', description: 'An active back-and-forth, not decided yet. Set it by hand while you talk it through.' },
    { label: 'Won', value: 'won', icon: 'i-lucide-trophy', description: 'Deal closed. Stops outreach here and at the whole company.' },
    { label: 'Lost', value: 'lost', icon: 'i-lucide-circle-slash', description: 'Said no, or it fell through. Stops outreach here and at the whole company.' },
    { label: 'Already a client', value: 'client', icon: 'i-lucide-handshake', description: 'Already buys from you. Stops outreach here and at the whole company - use for a client a search run should never cold-mail.' },
    { label: 'Not this one', value: 'rejected', icon: 'i-lucide-x', description: 'Not a fit for this project. Stops outreach here and at the whole company.' },
    { label: 'Opted out', value: 'suppressed', icon: 'i-lucide-ban', description: 'Asked to stop, or asked to be forgotten. Stops outreach for this person only - never spreads to colleagues at the same company.' }
]

/** The statuses that stop anything being sent. */
export const EXCLUDED_STATUSES: OutreachStatus[] = ['won', 'lost', 'client', 'rejected', 'suppressed']

export function isExcluded (status: OutreachStatus): boolean {
    return EXCLUDED_STATUSES.includes(status)
}
