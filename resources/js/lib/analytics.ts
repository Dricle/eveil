/**
 * Funnel milestones, reported to Rybbit from the browser.
 *
 * The names are a closed union rather than a free string on purpose: an event
 * is only worth recording if something downstream reads it, and a typo in a
 * string literal produces a second, empty funnel step that looks like a drop-off
 * instead of a bug. Add a case here first, then fire it.
 *
 * What is deliberately NOT here: anything with no browser behind it. The first
 * outreach mail actually leaving is a queue worker at 3am, and a completed
 * top-up is a Stripe webhook; neither has a page to run script on, and the
 * server-side ingest endpoint silently drops non-browser traffic rather than
 * erroring on it. Those milestones are counted from Postgres instead, by
 * `eveil:funnel-report`, which is exact and works retroactively. Treat this
 * file as attribution (where did the person come from, where did they stall)
 * and the database as the source of truth for whether something happened.
 */
export type FunnelEvent
    = | 'signup_started'
        | 'signup_completed'
        | 'organization_created'
        | 'analysis_started'
        | 'targets_derived'
        | 'lead_search_started'
        | 'sequence_created'
        | 'topup_checkout_started'
        | 'topup_completed'

type EventProperties = Record<string, string | number | boolean>

declare global {
    interface Window {
        rybbit?: {
            event: (name: string, properties?: EventProperties) => void
        }
    }
}

/**
 * Never throws and never blocks the action it is reporting on. The tag is
 * absent on every self-hosted instance (`components/analytics.blade.php`
 * renders nothing there) and on any cloud page loaded before the deferred
 * script arrives, so a missing `window.rybbit` is the normal case, not an
 * error worth surfacing to somebody who was trying to buy credits.
 */
export function track (event: FunnelEvent, properties: EventProperties = {}): void {
    try {
        window.rybbit?.event(event, properties)
    } catch {
        // Analytics must never be the reason a form submit fails.
    }
}
