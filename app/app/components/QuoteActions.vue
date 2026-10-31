<script setup lang="ts">
import type { Quote, StockWarning, TransitionTarget } from '~/types/api'

// Azioni per stato (docs/flows.md §2.6): il bottone principale cambia con lo stato.
// La chiusura cantiere (accepted → completed) arriva con F5.4
const props = defineProps<{
    quote: Quote
    hasLines: boolean
    /** `bar`: in basso su mobile, compatta; `panel`: pannello desktop */
    variant: 'bar' | 'panel'
}>()

const emit = defineEmits<{ changed: [quote: Quote] }>()

const quotes = useQuotes()
const toast = useToast()

const busy = ref<TransitionTarget | null>(null)
const confirm = ref<'rejected' | 'draft' | null>(null)
const warnings = ref<StockWarning[]>([])
const warningsOpen = ref(false)

const previewTo = computed(() => `/quotes/${props.quote.id}/preview`)

const DONE: Record<TransitionTarget, string> = {
    sent: 'Segnato come inviato',
    accepted: 'Preventivo accettato',
    rejected: 'Segnato come rifiutato',
    draft: 'Tornato in bozza: puoi modificarlo',
}

async function transition(to: TransitionTarget): Promise<void> {
    busy.value = to

    try {
        const response = await quotes.transition(props.quote.id, to)
        confirm.value = null
        emit('changed', response.data)
        toast.add({ title: DONE[to], color: 'success', icon: QUOTE_STATUSES[response.data.status].icon })

        if (response.stock_warnings.length > 0) {
            warnings.value = response.stock_warnings
            warningsOpen.value = true
        }
    }
    catch (error) {
        toast.add({ title: errorMessage(error), color: 'error', icon: 'i-lucide-circle-alert' })
    }
    finally {
        busy.value = null
    }
}
</script>

<template>
    <div
        class="flex flex-col"
        :class="variant === 'bar' ? 'gap-2' : 'gap-3'"
    >
        <!-- Bozza: si guarda il PDF e da lì si manda -->
        <template v-if="quote.status === 'draft'">
            <UButton
                :to="hasLines ? previewTo : undefined"
                label="Anteprima PDF"
                icon="i-lucide-file-text"
                size="xl"
                block
                :disabled="!hasLines"
                class="min-h-13 text-lg"
            />
            <p
                v-if="!hasLines"
                class="text-center text-sm text-cemento-600"
            >
                Aggiungi almeno un lavoro per vedere il PDF
            </p>
        </template>

        <!-- Inviato: si aspetta la risposta del cliente -->
        <template v-else-if="quote.status === 'sent'">
            <UButton
                label="Il cliente ha accettato"
                icon="i-lucide-check"
                size="xl"
                block
                :loading="busy === 'accepted'"
                class="min-h-13 text-lg"
                @click="transition('accepted')"
            />
            <div
                class="grid gap-2"
                :class="variant === 'bar' ? 'grid-cols-3' : 'grid-cols-1'"
            >
                <UButton
                    :label="variant === 'bar' ? 'Rifiutato' : 'Il cliente ha rifiutato'"
                    icon="i-lucide-x"
                    color="neutral"
                    variant="outline"
                    size="md"
                    class="justify-center"
                    @click="confirm = 'rejected'"
                />
                <UButton
                    :label="variant === 'bar' ? 'In bozza' : 'Torna in bozza'"
                    icon="i-lucide-pencil"
                    color="neutral"
                    variant="outline"
                    size="md"
                    class="justify-center"
                    @click="confirm = 'draft'"
                />
                <UButton
                    :to="previewTo"
                    :label="variant === 'bar' ? 'PDF' : 'Rivedi il PDF'"
                    icon="i-lucide-file-text"
                    color="neutral"
                    variant="outline"
                    size="md"
                    class="justify-center"
                />
            </div>
        </template>

        <!-- Accettato, chiuso, rifiutato: si consulta -->
        <UButton
            v-else
            :to="previewTo"
            label="Vedi il PDF"
            icon="i-lucide-file-text"
            color="neutral"
            variant="outline"
            block
            class="min-h-12"
        />

        <UModal
            :open="confirm !== null"
            :title="confirm === 'rejected' ? 'Il cliente ha rifiutato?' : 'Tornare in bozza?'"
            :description="confirm === 'rejected'
                ? 'Il preventivo resta in archivio come rifiutato. Se serve, potrai duplicarlo.'
                : 'Così puoi modificarlo. Poi andrà rimandato al cliente.'"
            @update:open="(value: boolean) => { if (!value) confirm = null }"
        >
            <template #footer>
                <div class="flex w-full flex-col gap-2 sm:flex-row-reverse">
                    <UButton
                        :label="confirm === 'rejected' ? 'Sì, rifiutato' : 'Sì, torna in bozza'"
                        :color="confirm === 'rejected' ? 'error' : 'primary'"
                        :loading="busy !== null"
                        class="justify-center"
                        @click="confirm && transition(confirm)"
                    />
                    <UButton
                        label="Annulla"
                        color="neutral"
                        variant="ghost"
                        class="justify-center"
                        @click="confirm = null"
                    />
                </div>
            </template>
        </UModal>

        <!-- Dopo l'accettazione: i materiali che vanno sotto scorta (docs/api.md transition) -->
        <UModal
            v-model:open="warningsOpen"
            title="Accettato! Ma manca del materiale"
            description="Per questo cantiere ti serve più di quello che hai in magazzino."
        >
            <template #body>
                <ul class="flex flex-col">
                    <li
                        v-for="warning in warnings"
                        :key="warning.material_id"
                        class="flex items-center justify-between gap-3 border-b border-cemento-200 py-3 last:border-b-0"
                    >
                        <span class="flex min-w-0 flex-col">
                            <span class="text-base font-bold text-cemento-900">{{ warning.name }}</span>
                            <span class="text-sm text-cemento-700">{{ warning.package_label }} · disponibili {{ formatQuantity(warning.available) }}</span>
                        </span>
                        <span class="shrink-0 rounded-full bg-cemento-900 px-3 py-1 text-sm font-bold text-white">compra {{ warning.to_buy }}</span>
                    </li>
                </ul>
            </template>
            <template #footer>
                <UButton
                    label="Ho capito"
                    block
                    class="justify-center"
                    @click="warningsOpen = false"
                />
            </template>
        </UModal>
    </div>
</template>
