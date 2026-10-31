// https://nuxt.com/docs/api/configuration/nuxt-config

// Sottocartella in cui gira l'app: "/" in locale, "/rasta/" su GitHub Pages (docs/stack.md "Ambiente di prova").
// Nuxt la legge da NUXT_APP_BASE_URL; qui serve anche alla PWA
const baseURL = process.env.NUXT_APP_BASE_URL ?? '/'

export default defineNuxtConfig({
    compatibilityDate: '2026-10-29',

    // SPA servita come statico (docs/stack.md)
    ssr: false,

    modules: [
        '@nuxt/ui',
        '@nuxt/eslint',
        '@pinia/nuxt',
        '@vite-pwa/nuxt',
    ],

    css: ['~/assets/css/main.css'],

    app: {
        baseURL,
        head: {
            htmlAttrs: { lang: 'it' },
            title: 'duemani',
            meta: [
                { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' },
                { name: 'theme-color', content: '#17181C' },
                { name: 'description', content: 'Preventivi e magazzino per chi lavora con le mani' },
            ],
        },
    },

    ui: {
        colorMode: false,
    },

    // Font scaricati in build e serviti dall'app: niente richieste a Google a runtime
    fonts: {
        defaults: {
            weights: [400, 500, 600, 700, 800],
        },
        families: [
            { name: 'Plus Jakarta Sans', provider: 'google' },
            { name: 'Bricolage Grotesque', provider: 'google', weights: [600, 800] },
        ],
    },

    icon: {
        serverBundle: 'local',
        clientBundle: {
            scan: true,
        },
    },

    // docs/stack.md "PWA": NetworkFirst per le API, CacheFirst per gli asset
    pwa: {
        registerType: 'autoUpdate',
        pwaAssets: {
            config: true,
        },
        manifest: {
            name: 'duemani',
            short_name: 'duemani',
            description: 'Preventivi e magazzino per chi lavora con le mani',
            lang: 'it',
            start_url: baseURL,
            scope: baseURL,
            display: 'standalone',
            orientation: 'portrait',
            theme_color: '#17181C',
            background_color: '#EFEEEA',
        },
        workbox: {
            navigateFallback: baseURL,
            globPatterns: ['**/*.{js,css,html,svg,png,ico,woff2}'],
            runtimeCaching: [
                {
                    urlPattern: ({ url }) => url.pathname.startsWith('/api/'),
                    handler: 'NetworkFirst',
                    options: {
                        cacheName: 'api',
                        networkTimeoutSeconds: 5,
                    },
                },
                {
                    urlPattern: ({ request }) => ['font', 'image', 'style', 'script'].includes(request.destination),
                    handler: 'CacheFirst',
                    options: {
                        cacheName: 'assets',
                        expiration: { maxEntries: 200 },
                    },
                },
            ],
        },
        client: {
            installPrompt: true,
        },
        devOptions: {
            enabled: false,
        },
    },

    devtools: {
        enabled: false,
    },

    runtimeConfig: {
        public: {
            apiBase: 'https://app-artigiani.ddev.site/api',
        },
    },
})
