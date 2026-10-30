<script setup lang="ts">
import type { Quote } from '~/types/api'

// Dettaglio preventivo (docs/flows.md §2.3). F4.3: solo l'intestazione; righe e totali arrivano con F4.4
const route = useRoute()
const quotes = useQuotes()

const quote = ref<Quote | null>(null)
const failed = ref(false)

useHead(() => ({ title: quote.value ? `n. ${quote.value.number_label} · duemani` : 'Preventivo · duemani' }))

onMounted(async () => {
    try {
        quote.value = (await quotes.get(String(route.params.id))).data
    }
    catch {
        failed.value = true
    }
})
</script>

<template>
    <section class="flex flex-col gap-6">
        <UButton
            to="/quotes"
            label="Preventivi"
            icon="i-lucide-arrow-left"
            color="neutral"
            variant="link"
            class="-mx-2.5 self-start"
        />

        <p
            v-if="failed"
            class="rounded-3xl bg-white px-6 py-10 text-center text-base text-cemento-700"
        >
            Non trovo questo preventivo.
        </p>

        <USkeleton
            v-else-if="!quote"
            class="h-28 rounded-3xl bg-white"
        />

        <template v-else>
            <div class="flex flex-col gap-2 rounded-3xl bg-white p-5">
                <div class="flex items-start justify-between gap-3">
                    <span class="text-base font-semibold text-cemento-700">n. {{ quote.number_label }} · {{ formatDate(quote.issued_at) }}</span>
                    <QuoteStatusBadge :status="quote.status" />
                </div>
                <h1 class="font-display text-3xl leading-tight font-extrabold tracking-tight text-cemento-900">
                    {{ quote.customer?.name }}
                </h1>
                <span class="text-lg text-cemento-700">{{ quote.site?.name }}</span>
            </div>

            <div class="flex flex-col items-center gap-3 rounded-3xl bg-white px-6 py-10 text-center">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-cemento-900 text-primary">
                    <UIcon
                        name="i-lucide-paint-roller"
                        class="size-7"
                    />
                </span>
                <p class="max-w-sm text-base text-cemento-700">
                    Il preventivo è creato. Qui aggiungerai le lavorazioni e vedrai materiali e totale.
                </p>
            </div>
        </template>
    </section>
</template>
