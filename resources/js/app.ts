import { createInertiaApp } from '@inertiajs/vue3';
import ui from '@nuxt/ui/vue-plugin';
import RootLayout from '@/layouts/RootLayout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
    // Every page renders inside <UApp>, which Nuxt UI needs for toasts,
    // overlays and tooltips.
    layout: () => RootLayout,
    withApp: (app): void => {
        app.use(ui);
    },
});
