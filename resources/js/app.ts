import { createInertiaApp } from '@inertiajs/vue3'
import UApp from '@nuxt/ui/components/App.vue'
import ui from '@nuxt/ui/vue-plugin'
import { createApp, h } from 'vue'

const appName = 'Eveil'

createInertiaApp({
    title: title => (title ? `${title} · ${appName}` : appName),
    progress: {
        color: '#4B5563'
    },
    // Every page renders inside <UApp>, which Nuxt UI needs for toasts,
    // overlays and tooltips. Wrapped around the root here, not given as the
    // default layout: Inertia only applies a default layout to a page that
    // declares none, and nearly every page declares `AppLayout`, so those
    // pages ended up with no <UApp> above them at all.
    setup ({ el, App, props, plugin }) {
        createApp({ render: () => h(UApp, null, () => h(App, props)) })
            .use(plugin)
            .use(ui)
            .mount(el!)
    }
})
