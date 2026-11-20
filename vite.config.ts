import inertia from '@inertiajs/vite'
import { wayfinder } from '@laravel/vite-plugin-wayfinder'
import ui from '@nuxt/ui/vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import { local } from 'laravel-vite-plugin/fonts'
import { defineConfig } from 'vite'

// One variant per committed file: `local()` given a directory merges every
// file into a single 400 weight face.
const localFont = (family: string, slug: string, weights: (400 | 500 | 600 | 700)[]) => local(family, {
    variants: weights.map(weight => ({ src: `resources/fonts/${slug}/${slug}-${weight}-normal.woff2`, weight }))
})

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            // Committed woff2 files (latin subset, from Fontsource) rather than a
            // font CDN fetched at build time: a CDN outage used to fail every build.
            fonts: [
                localFont('Geist', 'geist', [400, 500, 600, 700]),
                // Marketing site only (resources/views/marketing/**): Sora for
                // headings, Geist Mono for the eyebrow/label/tabular-number look.
                localFont('Sora', 'sora', [400, 600, 700]),
                localFont('Geist Mono', 'geist-mono', [400, 500])
            ]
        }),
        inertia(),
        // Nuxt UI registers @tailwindcss/vite itself - adding it here too
        // would run Tailwind twice. `router: 'inertia'` swaps its ULink
        // internals off vue-router, which this app does not have.
        ui({
            router: 'inertia',
            // Vue (non-Nuxt) has no app.config.ts - the theme colors the Nuxt UI
            // builder puts there are passed to the plugin instead.
            ui: {
                colors: {
                    primary: 'cyan',
                    secondary: 'violet',
                    neutral: 'mist'
                },
                // Menus portal into <body> with no z-index of their own, so the
                // sidebar's fixed `z-10` container paints over them.
                dropdownMenu: {
                    slots: {
                        content: 'z-20'
                    }
                }
            }
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false
                }
            }
        }),
        wayfinder({
            formVariants: true
        })
    ]
})
