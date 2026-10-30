<script setup lang="ts">
import type { Quote } from '~/types/api'

// Card della lista (docs/flows.md §2.1): numero · cliente · cantiere · totale · stato · data
defineProps<{ quote: Quote }>()
</script>

<template>
    <NuxtLink
        :to="`/quotes/${quote.id}`"
        class="flex flex-col gap-2 rounded-2xl bg-white p-4 transition-shadow hover:shadow-md focus-visible:outline-3 focus-visible:outline-primary"
    >
        <span class="flex items-start justify-between gap-3">
            <span class="min-w-0 text-lg leading-snug font-bold text-cemento-900">{{ quote.customer?.name ?? 'Senza cliente' }}</span>
            <QuoteStatusBadge :status="quote.status" />
        </span>
        <span class="flex items-end justify-between gap-3">
            <span class="flex min-w-0 flex-col text-base text-cemento-700">
                <span class="truncate">{{ quote.site?.name }}</span>
                <span>n. {{ quote.number_label }} · {{ formatDate(quote.issued_at) }}</span>
            </span>
            <span class="shrink-0 text-lg font-extrabold text-cemento-900 tabular-nums">{{ formatEuro(quote.total) }}</span>
        </span>
    </NuxtLink>
</template>
