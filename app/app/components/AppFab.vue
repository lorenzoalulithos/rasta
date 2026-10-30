<script setup lang="ts">
// FAB "+" → nuovo preventivo, su Home e Preventivi (docs/flows.md §0)
</script>

<template>
    <UButton
        to="/quotes/new"
        icon="i-lucide-plus"
        label="Nuovo preventivo"
        size="xl"
        class="fixed right-4 bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-20 min-h-14 rounded-full px-5 text-base shadow-lg lg:right-10 lg:bottom-10"
        :ui="{ leadingIcon: 'size-6' }"
    />
</template>
