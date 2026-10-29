// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
    compatibilityDate: '2026-10-29',

    // SPA servita come statico (docs/stack.md)
    ssr: false,

    modules: [
        '@nuxt/ui',
        '@nuxt/eslint',
        '@pinia/nuxt',
    ],

    css: ['~/assets/css/main.css'],

    app: {
        head: {
            htmlAttrs: { lang: 'it' },
            title: 'App Artigiani',
        },
    },

    ui: {
        colorMode: false,
    },

    icon: {
        serverBundle: 'local',
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
