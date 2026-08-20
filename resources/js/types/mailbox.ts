/**
 * A mailbox as the settings screen sees it. No passwords: they are write-only
 * from the UI's point of view, so an edit form that leaves them blank means
 * "keep the one you have".
 */
export type Mailbox = {
    id: number
    name: string
    from_name: string
    from_email: string
    smtp_host: string
    smtp_port: number
    smtp_username: string
    smtp_encryption: string | null
    imap_host: string
    imap_port: number
    imap_username: string
    imap_encryption: string | null
    signature: string | null
    daily_limit: number
    status: 'active' | 'paused' | 'error'
    last_error: string | null
    last_checked_at: string | null
    /** Today's figure, which is the daily limit unless a ramp-up is running. */
    allowance_today: number
    remaining_today: number
    sent_today: number
    ramping_up: boolean
    /** The projects granted this mailbox, as ids, because it is a checkbox list. */
    projects: number[]
}

/**
 * Host and port pairs for the providers this app's users actually have, plus
 * the one sentence each that decides whether a setup works. The Google and
 * Microsoft notes are the expensive ones: plain SMTP is off by default on both,
 * and neither says so in a way anybody reads before typing their password.
 */
export const PROVIDER_PRESETS = [
    {
        label: 'Infomaniak',
        smtp_host: 'mail.infomaniak.com',
        smtp_port: 587,
        smtp_encryption: 'starttls',
        imap_host: 'mail.infomaniak.com',
        imap_port: 993,
        imap_encryption: 'tls',
        note: 'Log in with the full address. Works with the mailbox password.'
    },
    {
        label: 'OVH',
        smtp_host: 'ssl0.ovh.net',
        smtp_port: 587,
        smtp_encryption: 'starttls',
        imap_host: 'ssl0.ovh.net',
        imap_port: 993,
        imap_encryption: 'tls',
        note: 'Log in with the full address, not the account reference.'
    },
    {
        label: 'Gandi',
        smtp_host: 'mail.gandi.net',
        smtp_port: 587,
        smtp_encryption: 'starttls',
        imap_host: 'mail.gandi.net',
        imap_port: 993,
        imap_encryption: 'tls',
        note: 'The mailbox password, not your Gandi account password.'
    },
    {
        label: 'Zoho',
        smtp_host: 'smtp.zoho.eu',
        smtp_port: 587,
        smtp_encryption: 'starttls',
        imap_host: 'imap.zoho.eu',
        imap_port: 993,
        imap_encryption: 'tls',
        note: 'Use .com hosts if your account was created outside Europe. IMAP has to be enabled once under Mail settings.'
    },
    {
        label: 'Gmail / Workspace',
        smtp_host: 'smtp.gmail.com',
        smtp_port: 587,
        smtp_encryption: 'starttls',
        imap_host: 'imap.gmail.com',
        imap_port: 993,
        imap_encryption: 'tls',
        note: 'Needs an app password, not the account password: turn on 2-step verification first, then generate one. A Workspace admin can block app passwords for the whole organization.'
    },
    {
        label: 'Microsoft 365',
        smtp_host: 'smtp.office365.com',
        smtp_port: 587,
        smtp_encryption: 'starttls',
        imap_host: 'outlook.office365.com',
        imap_port: 993,
        imap_encryption: 'tls',
        note: 'SMTP AUTH is off by default on most tenants; an admin enables it per mailbox in the Exchange admin center. Microsoft has announced its removal for 2027.'
    }
] as const
