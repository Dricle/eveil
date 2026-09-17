/**
 * The URL to land on after switching the sidebar's project, keeping the
 * current page rather than always dropping to the dashboard: swap the
 * `{project:slug}` segment and keep the rest of the path, UNLESS it names a
 * specific record (`companies/42`) that almost certainly does not exist in
 * the target project - truncate at the first purely-numeric segment instead,
 * landing on that section's index (`companies`).
 */
export function switchProjectUrl (newSlug: string): string {
    const segments = window.location.pathname.split('/')
    const projectIndex = 2 // ['', 'app', '{project}', ...]

    const cutAt = segments.findIndex((segment, index) => index > projectIndex && /^\d+$/.test(segment))
    const kept = cutAt === -1 ? segments : segments.slice(0, cutAt)

    kept[projectIndex] = newSlug

    return kept.join('/') + window.location.search
}
