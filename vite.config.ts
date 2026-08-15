import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import ui from '@nuxt/ui/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Geist', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        inertia(),
        // Nuxt UI registers @tailwindcss/vite itself — adding it here too
        // would run Tailwind twice. `router: 'inertia'` swaps its ULink
        // internals off vue-router, which this app does not have.
        ui({
            router: 'inertia',
            // Vue (non-Nuxt) has no app.config.ts — the theme colors the Nuxt UI
            // builder puts there are passed to the plugin instead.
            ui: {
                colors: {
                    primary: 'cyan',
                    neutral: 'neutral',
                },
            },
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
});
