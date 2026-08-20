/**
 * What a verification verdict and an address's provenance mean, in one place.
 *
 * Both were copied across three screens before this, which is how the contacts
 * list and the contact sheet ended up able to disagree about what "risky"
 * means. Nothing is hidden behind a "verified" label: an address guessed from a
 * pattern says so, because the person sending is the one whose domain takes the
 * complaints.
 */
export const VERIFICATION = {
    valid: { color: 'success' as const, label: 'Verified', help: 'The server accepted the address.' },
    unknown: { color: 'neutral' as const, label: 'Unverified', help: 'The provider blocks checks. Gmail and Outlook always do.' },
    risky: { color: 'warning' as const, label: 'Catch-all', help: 'The domain accepts everything, so acceptance proves nothing.' },
    invalid: { color: 'error' as const, label: 'Invalid', help: 'Rejected by the server. Never sent to.' }
}

export const SOURCES = {
    scraped: 'Published on the site',
    inferred: 'Guessed from another address on the domain',
    provided: 'Given by the user',
    imported: 'Imported'
}

export type Verification = keyof typeof VERIFICATION
export type Source = keyof typeof SOURCES
