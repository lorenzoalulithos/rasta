// https://nuxt.com/docs/api/configuration/nuxt-config

export default defineNuxtConfig({
    future: {
        compatibilityVersion: 4,
    },

    srcDir: 'nuxt/app',

    serverDir: 'nuxt/server/',
    
    dir: {
        public: 'nuxt/public/'
    },

    $development: {
        ssr: true,
        devtools: {
            enabled: false,
        },
    },

    $production: {
        ssr: true,
    },

    ui: {
        colorMode: false
    },

    robots: {
        disallow: process.env.NUXT_SITE_ENV === 'staging' ? ['*'] : [],
        allow: process.env.NUXT_SITE_ENV === 'staging' ? [] : ['*'],
    },

    app: {
        head: {
            title: 'rasta',
            meta: [
                {charset: 'utf-8'},
                {name: 'viewport', content: 'width=device-width, initial-scale=1'},
            ],
            link: [
                {rel: 'icon', type: 'image/x-icon', href: '/favicon.ico'},
            ],
        },
    },

    /**
     * @see https://v3.nuxtjs.org/api/configuration/nuxt.config#modules
     */
    modules: [
        '@nuxt/ui',
        '@nuxt/image',
        'dayjs-nuxt',
        'nuxt-security',
        '@nuxtjs/seo',
        '@nuxtjs/i18n',
    ],

    i18n: {
        defaultLocale: 'it',
        locales: [
            {
                'code': 'it',
                'iso': 'it-IT',
                'file': 'it.js',
            },
        ],
        langDir: '../lang/',
        compilation: {
            strictMessage: false,
        },
    },


    css: ['~/assets/css/main.css'],

    compatibilityDate: '2025-05-09',

    image: {
        domains: [
            process.env.API_URL || 'http://127.0.0.1:8000'
        ],
        alias: {
            api: process.env.API_URL || 'http://127.0.0.1:8000'
        }
    },

    security: {
        headers: {
            crossOriginEmbedderPolicy: 'unsafe-none',
            crossOriginOpenerPolicy: 'same-origin-allow-popups',
            contentSecurityPolicy: {
                'img-src': ['\'self\'', 'data:', 'https://*', process.env.API_URL || 'http://127.0.0.1:8000'],
            },
        },
    },

    dayjs: {
        locales: ['en'],
        plugins: ['relativeTime', 'utc', 'timezone'],
        defaultLocale: 'en',
        defaultTimezone: process.env.APP_TIMEZONE,
    },

    postcss: {
        plugins: {
            '@tailwindcss/postcss': {},
        },
    },

    typescript: {
        strict: false,
    },

    /**
     * @see https://v3.nuxtjs.org/guide/features/runtime-config#exposing-runtime-config
     */
    runtimeConfig: {
        apiLocal: process.env.API_LOCAL_URL,
        public: {
            apiBase: process.env.API_URL,
            apiPrefix: '/api/v1',
            storageBase: process.env.API_URL + '/storage/',
        },
    },
})