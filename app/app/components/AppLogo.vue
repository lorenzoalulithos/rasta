<script setup lang="ts">
// Marchio duemani: due pennellate, la prima mano e la seconda (direzione B)
withDefaults(defineProps<{
    /** `dark`: su fondo nero, la seconda pennellata diventa chiara */
    tone?: 'light' | 'dark'
    showName?: boolean
}>(), {
    tone: 'light',
    showName: true,
})
</script>

<template>
    <span class="inline-flex items-center gap-2">
        <svg
            class="size-7 shrink-0"
            viewBox="0 0 100 100"
            aria-hidden="true"
        >
            <rect
                x="10"
                y="26"
                width="80"
                height="24"
                rx="12"
                :fill="tone === 'dark' ? '#7A5A12' : '#E8A317'"
                transform="rotate(-14 50 50)"
            />
            <rect
                x="10"
                y="52"
                width="80"
                height="24"
                rx="12"
                :fill="tone === 'dark' ? '#E8A317' : '#17181C'"
                transform="rotate(-14 50 50)"
            />
        </svg>
        <span
            v-if="showName"
            class="font-display text-2xl font-extrabold tracking-tight"
            :class="tone === 'dark' ? 'text-white' : 'text-cemento-900'"
        >duemani</span>
    </span>
</template>
