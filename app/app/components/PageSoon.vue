<script setup lang="ts">
// Segnaposto delle sezioni non ancora costruite (F4.3 e seguenti in docs/roadmap.md)
defineProps<{
    title: string
    icon: string
    text: string
}>()
</script>

<template>
    <section class="flex flex-col gap-6">
        <h1 class="font-display text-4xl font-extrabold tracking-tight text-cemento-900">
            {{ title }}
        </h1>
        <div class="flex flex-col items-center gap-3 rounded-3xl bg-white px-6 py-12 text-center">
            <span class="flex size-14 items-center justify-center rounded-2xl bg-cemento-900 text-primary">
                <UIcon
                    :name="icon"
                    class="size-7"
                />
            </span>
            <p class="max-w-sm text-base text-cemento-700">
                {{ text }}
            </p>
        </div>
    </section>
</template>
