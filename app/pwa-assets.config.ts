import { defineConfig, minimal2023Preset } from '@vite-pwa/assets-generator/config'

// Icone della PWA generate dal marchio (direzione B, docs/stack.md "Brand")
export default defineConfig({
    headLinkOptions: {
        preset: '2023',
    },
    preset: {
        ...minimal2023Preset,
        maskable: {
            ...minimal2023Preset.maskable,
            resizeOptions: { background: '#E8A317' },
        },
        apple: {
            ...minimal2023Preset.apple,
            resizeOptions: { background: '#E8A317' },
        },
    },
    images: ['public/pwa-icon.svg'],
})
