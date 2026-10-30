<script setup lang="ts">
// Home (docs/flows.md §1): i tre blocchi arrivano con F5.5, qui solo il saluto
useHead({ title: 'duemani' })

const auth = useAuthStore()

const firstName = computed(() => auth.user?.name.split(' ')[0] ?? '')
const date = new Intl.DateTimeFormat('it-IT', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date())
// "Venerdì 30 ottobre": maiuscola solo all'inizio
const today = date.charAt(0).toUpperCase() + date.slice(1)
</script>

<template>
    <section class="flex flex-col gap-6">
        <div class="flex flex-col gap-1">
            <span class="text-base font-semibold text-cemento-700">{{ today }}</span>
            <h1 class="font-display text-4xl font-extrabold tracking-tight text-cemento-900">
                Ciao{{ firstName ? ` ${firstName}` : '' }}
            </h1>
        </div>

        <div class="flex flex-col items-center gap-3 rounded-3xl bg-white px-6 py-12 text-center">
            <span class="flex size-14 items-center justify-center rounded-2xl bg-cemento-900 text-primary">
                <UIcon
                    name="i-lucide-paintbrush"
                    class="size-7"
                />
            </span>
            <p class="max-w-sm text-base text-cemento-700">
                Qui troverai i preventivi aperti, cosa comprare e i cantieri da chiudere.
            </p>
        </div>

        <AppFab />
    </section>
</template>
