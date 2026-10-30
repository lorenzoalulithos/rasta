<script setup lang="ts">
// Bottom sheet su mobile, pannello laterale da lg in su (docs/flows.md "Principi UI")
defineProps<{
    title: string
    description?: string
}>()

const open = defineModel<boolean>('open', { default: false })
const isDesktop = useIsDesktop()
</script>

<template>
    <UDrawer
        v-model:open="open"
        :title="title"
        :description="description"
        :direction="isDesktop ? 'right' : 'bottom'"
        :handle="!isDesktop"
        :ui="{
            content: isDesktop ? 'w-full max-w-md' : 'max-h-[92dvh]',
            header: 'px-5 pt-4',
            title: 'font-display text-2xl font-extrabold tracking-tight text-cemento-900',
            description: 'text-base text-cemento-700',
            body: 'px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] overflow-y-auto',
        }"
    >
        <template #body>
            <slot />
        </template>
    </UDrawer>
</template>
