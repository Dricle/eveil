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
export type OutreachStatus = 'new' | 'queued' | 'contacted' | 'replied' | 'won' | 'lost' | 'client' | 'rejected' | 'suppressed'

export type StatusOption = { label: string, value: OutreachStatus, icon: string }

export const OUTREACH_STATUSES: StatusOption[] = [
    { label: 'New', value: 'new', icon: 'i-lucide-circle-dashed' },
    { label: 'Queued', value: 'queued', icon: 'i-lucide-clock' },
    { label: 'Contacted', value: 'contacted', icon: 'i-lucide-send' },
    { label: 'Replied', value: 'replied', icon: 'i-lucide-message-square' },
    { label: 'Won', value: 'won', icon: 'i-lucide-trophy' },
    { label: 'Lost', value: 'lost', icon: 'i-lucide-circle-slash' },
    { label: 'Already a client', value: 'client', icon: 'i-lucide-handshake' },
    { label: 'Not this one', value: 'rejected', icon: 'i-lucide-x' },
    { label: 'Unsubscribed', value: 'suppressed', icon: 'i-lucide-ban' }
]

/** The statuses that stop anything being sent. */
export const EXCLUDED_STATUSES: OutreachStatus[] = ['won', 'lost', 'client', 'rejected', 'suppressed']

export function isExcluded (status: OutreachStatus): boolean {
    return EXCLUDED_STATUSES.includes(status)
}
