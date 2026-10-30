<script setup lang="ts">
import type { QuoteTotals } from '~/types/api'

// Totale per il cliente e, in piccolo, costo e margine interni (mai nel PDF, docs/flows.md §2.3).
// Tap su costo/margine: confronto "a costi netti IVA" (domain.md §7.3 opzione c), calcolato al volo
const props = defineProps<{
    totals: QuoteTotals
    /** `bar`: barra in basso su mobile; `panel`: riepilogo del pannello desktop */
    variant: 'bar' | 'panel'
}>()

const VAT_FACTOR = 1.22

const showNet = ref(false)

const percent = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 0 })

const net = computed(() => {
    const cost = props.totals.estimated_cost / VAT_FACTOR
    const margin = props.totals.subtotal > 0 ? (props.totals.subtotal - cost) / props.totals.subtotal * 100 : null

    return { cost, margin }
})

function pct(value: number | null): string {
    return value === null ? '–' : `${percent.format(value)}%`
}
</script>

<template>
    <div
        class="flex flex-col"
        :class="variant === 'bar' ? 'gap-0.5' : 'gap-3'"
    >
        <template v-if="variant === 'panel'">
            <div class="flex justify-between text-base text-cemento-700">
                <span>Imponibile</span>
                <span class="tabular-nums">{{ formatEuro(totals.subtotal) }}</span>
            </div>
            <div class="flex justify-between text-base text-cemento-700">
                <span>IVA</span>
                <span class="tabular-nums">{{ formatEuro(totals.vat_amount) }}</span>
            </div>
        </template>

        <div class="flex items-baseline justify-between gap-3">
            <span
                class="font-bold text-cemento-900"
                :class="variant === 'bar' ? 'text-base' : 'text-lg'"
            >Totale</span>
            <span
                class="font-extrabold text-cemento-900 tabular-nums"
                :class="variant === 'bar' ? 'text-2xl' : 'text-3xl'"
            >{{ formatEuro(totals.total) }}</span>
        </div>

        <button
            type="button"
            class="flex flex-col gap-0.5 rounded-lg text-left text-sm text-cemento-600 focus-visible:outline-2 focus-visible:outline-primary"
            :aria-expanded="showNet"
            @click="showNet = !showNet"
        >
            <span class="flex items-center gap-1">
                <UIcon
                    name="i-lucide-lock"
                    class="size-3.5 shrink-0"
                />
                <span>Costo stimato {{ formatEuro(totals.estimated_cost) }} · Margine {{ pct(totals.estimated_margin_pct) }}</span>
                <UIcon
                    name="i-lucide-chevron-down"
                    class="size-4 shrink-0 transition-transform"
                    :class="showNet ? 'rotate-180' : ''"
                />
            </span>
            <span v-if="showNet">a costi netti IVA: costo {{ formatEuro(net.cost) }} · margine {{ pct(net.margin) }}</span>
        </button>
    </div>
</template>
