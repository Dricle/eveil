import { defineConfig } from 'vitepress';

export default defineConfig({
    title: 'Eveil Docs',
    description: 'Self-hosted install/config and usage docs for Eveil, the open-source alternative to lemlist.',
    cleanUrls: true,
    themeConfig: {
        nav: [
            { text: 'Guide', link: '/guide/introduction' },
            { text: 'Usage', link: '/usage/getting-started' },
            { text: 'eveil.cloud', link: 'https://eveil.cloud' },
        ],
        sidebar: {
            '/guide/': [
                {
                    text: 'Self-hosting',
                    items: [
                        { text: 'Introduction', link: '/guide/introduction' },
                        { text: 'Installation', link: '/guide/installation' },
                        { text: 'Configuration', link: '/guide/configuration' },
                    ],
                },
            ],
            '/usage/': [
                {
                    text: 'Using Eveil',
                    items: [
                        { text: 'Getting started', link: '/usage/getting-started' },
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
