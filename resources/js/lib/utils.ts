import { clsx } from 'clsx'
import type { ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn (...inputs: ClassValue[]) {
    return twMerge(clsx(inputs))
}

// Wayfinder's `.url()` returns an absolute URL in prod (AppServiceProvider
// forces the app's own root URL onto every generated link, for password-reset
// email safety) but a relative one locally. Nuxt UI's Inertia `Link` treats
// any URL carrying a protocol as external and skips SPA interception, so an
// absolute same-origin URL silently turns internal navigation into a full
// page reload. Strip the origin before handing a Wayfinder URL to a
// `to`/`href` prop that should stay an SPA visit.
export function relativeUrl (url: string): string {
    return url.replace(/^https?:\/\/[^/]+/, '')
}
