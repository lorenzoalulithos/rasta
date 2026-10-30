<script setup lang="ts" generic="T extends string | number">
// Scelta tra poche opzioni a tutta larghezza (mq / ore / unità…, 1 / 2 / 3 mani): UTabs senza contenuto
defineProps<{
    items: { label: string, value: T }[]
    label: string
}>()

const model = defineModel<T>()
</script>

<template>
    <UTabs
        v-model="model"
        :items="items"
        :content="false"
        color="neutral"
        variant="pill"
        size="xl"
        :aria-label="label"
        class="w-full"
        :ui="{
            list: 'w-full rounded-xl bg-white ring ring-cemento-300',
            indicator: 'rounded-lg bg-cemento-900',
            trigger: 'min-w-0 flex-1 justify-center px-0.5 py-2.5 text-[15px] font-bold data-[state=active]:text-white data-[state=inactive]:text-cemento-800',
            label: 'overflow-visible whitespace-nowrap',
        }"
    />
</template>
