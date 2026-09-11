import { defineConfig } from 'vitepress';

export default defineConfig({
    title: 'Eveil Docs',
    description: 'How Eveil works, and how to self-host it: the open-source alternative to lemlist.',
    cleanUrls: true,
    themeConfig: {
        nav: [
            { text: 'Introduction', link: '/intro' },
            { text: 'Product', link: '/product/getting-started' },
            { text: 'Self-hosting', link: '/self-hosted/installation' },
            { text: 'eveil.cloud', link: 'https://eveil.cloud' },
        ],
        sidebar: {
            '/product/': [
                {
                    text: 'Using Eveil',
                    items: [
                        { text: 'Getting started', link: '/product/getting-started' },
                        { text: 'Statuses and the inbox', link: '/product/statuses' },
                    ],
                },
            ],
            // Self-hosting is its own sidebar, shown only under /self-hosted/:
            // deliberately the LAST section a cloud user ever needs to open.
            '/self-hosted/': [
                {
                    text: 'Self-hosting',
                    items: [
                        { text: 'Installation', link: '/self-hosted/installation' },
                        { text: 'Configuration', link: '/self-hosted/configuration' },
                        { text: 'Commands', link: '/self-hosted/commands' },
                        { text: 'Updating', link: '/self-hosted/updating' },
                        { text: 'Backup', link: '/self-hosted/backup' },
                    ],
                },
            ],
        },
        socialLinks: [
            { icon: 'github', link: 'https://github.com/Dricle/eveil' },
        ],
        search: {
            provider: 'local',
        },
    },
});
