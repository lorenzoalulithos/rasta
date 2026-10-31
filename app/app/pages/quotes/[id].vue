<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import type { Job, Quote, QuoteLine } from '~/types/api'

// Dettaglio preventivo, step 2: righe e totali (docs/flows.md §2.3)
const route = useRoute()
const quotes = useQuotes()
const toast = useToast()

const id = computed(() => String(route.params.id))
const quote = ref<Quote | null>(null)
const failed = ref(false)
const pickerOpen = ref(false)
const lineOpen = ref(false)
const editingLine = ref<QuoteLine | null>(null)
const newJob = ref<Job | null>(null)
const notesOpen = ref(false)
const deleteOpen = ref(false)
const busy = ref(false)

useHead(() => ({ title: quote.value ? `n. ${quote.value.number_label} · duemani` : 'Preventivo · duemani' }))

// Righe e materiali si cambiano solo in bozza e inviato (domain.md §6)
const editable = computed(() => quote.value?.status === 'draft' || quote.value?.status === 'sent')
const lines = computed(() => quote.value?.lines ?? [])

async function load(): Promise<void> {
    try {
        quote.value = (await quotes.get(id.value)).data
        failed.value = false
    }
    catch {
        failed.value = true
    }
}

onMounted(load)

async function lineSaved(message: string): Promise<void> {
    await load()
    toast.add({ title: message, color: 'success', icon: 'i-lucide-check' })
}

// Il picker si chiude e si apre la riga (§2.4): un attimo di pausa per non sovrapporre i due pannelli
const SHEET_SWAP_MS = 250

function chooseJob(job: Job | null): void {
    editingLine.value = null
    newJob.value = job
    setTimeout(() => {
        lineOpen.value = true
    }, SHEET_SWAP_MS)
}

async function removeLine(line: QuoteLine): Promise<void> {
    if (!line.id) {
        return
    }

    try {
        await quotes.removeLine(id.value, line.id)
        await load()
        toast.add({ title: `"${line.description}" eliminata`, color: 'neutral', icon: 'i-lucide-trash-2' })
    }
    catch (error) {
        toast.add({ title: errorMessage(error), color: 'error', icon: 'i-lucide-circle-alert' })
    }
}

function openLine(line: QuoteLine): void {
    if (!editable.value) {
        return
    }

    editingLine.value = line
    newJob.value = null
    lineOpen.value = true
}

// ── Menu ⋯: note private, duplica, elimina ──
const notes = ref('')

function editNotes(): void {
    notes.value = quote.value?.private_notes ?? ''
    notesOpen.value = true
}

async function saveNotes(): Promise<void> {
    busy.value = true

    try {
        const response = await quotes.update(id.value, { private_notes: notes.value.trim() || null })
        quote.value = { ...response.data, lines: quote.value?.lines }
        notesOpen.value = false
        toast.add({ title: 'Note salvate', color: 'success', icon: 'i-lucide-check' })
    }
    catch (error) {
        toast.add({ title: errorMessage(error), color: 'error', icon: 'i-lucide-circle-alert' })
    }
    finally {
        busy.value = false
    }
}

async function duplicate(): Promise<void> {
    try {
        const response = await quotes.duplicate(id.value)
        toast.add({ title: `Copia creata: n. ${response.data.number_label}`, color: 'success', icon: 'i-lucide-copy' })
        await navigateTo(`/quotes/${response.data.id}`)
    }
    catch (error) {
        toast.add({ title: errorMessage(error), color: 'error', icon: 'i-lucide-circle-alert' })
    }
}

async function remove(): Promise<void> {
    busy.value = true

    try {
        await quotes.remove(id.value)
        toast.add({ title: 'Preventivo eliminato', color: 'neutral', icon: 'i-lucide-trash-2' })
        await navigateTo('/quotes', { replace: true })
    }
    catch (error) {
        toast.add({ title: errorMessage(error), color: 'error', icon: 'i-lucide-circle-alert' })
    }
    finally {
        busy.value = false
        deleteOpen.value = false
    }
}

// Si rilegge quando cambia l'id (es. dopo "Duplica")
watch(id, () => {
    quote.value = null
    load()
})

const menu = computed<DropdownMenuItem[][]>(() => [
    [
        { label: 'Note private', icon: 'i-lucide-notebook-pen', onSelect: editNotes },
        { label: 'Duplica', icon: 'i-lucide-copy', onSelect: duplicate },
    ],
    quote.value?.status === 'draft'
        ? [{ label: 'Elimina preventivo', icon: 'i-lucide-trash-2', color: 'error' as const, onSelect: () => { deleteOpen.value = true } }]
        : [],
])
</script>

<template>
    <section class="flex flex-col gap-5 pb-36 lg:pb-0">
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

        <div
            v-else-if="!quote"
            class="flex flex-col gap-3"
        >
            <USkeleton class="h-28 rounded-3xl bg-white" />
            <USkeleton class="h-24 rounded-2xl bg-white" />
            <USkeleton class="h-24 rounded-2xl bg-white" />
        </div>

        <div
            v-else
            class="grid gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(18rem,2fr)] lg:items-start lg:gap-8"
        >
            <!-- Colonna righe -->
            <div class="flex min-w-0 flex-col gap-4">
                <header class="flex flex-col gap-2 rounded-3xl bg-white p-5">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold whitespace-nowrap text-cemento-700 sm:text-base">n. {{ quote.number_label }} · {{ formatDate(quote.issued_at) }}</span>
                        <div class="flex items-center gap-1">
                            <QuoteStatusBadge :status="quote.status" />
                            <UDropdownMenu
                                :items="menu"
                                :content="{ align: 'end' }"
                                :ui="{ item: 'py-3 text-base' }"
                            >
                                <UButton
                                    icon="i-lucide-ellipsis-vertical"
                                    color="neutral"
                                    variant="ghost"
                                    aria-label="Altre azioni"
                                    class="-mr-2"
                                />
                            </UDropdownMenu>
                        </div>
                    </div>
                    <h1 class="font-display text-3xl leading-tight font-extrabold tracking-tight text-cemento-900">
                        {{ quote.customer?.name }}
                    </h1>
                    <span class="text-lg text-cemento-700">{{ quote.site?.name }}</span>
                    <button
                        v-if="quote.private_notes"
                        type="button"
                        class="mt-1 flex items-start gap-2 rounded-xl bg-cemento-100 p-3 text-left text-base text-cemento-800 lg:hidden"
                        @click="editNotes"
                    >
                        <UIcon
                            name="i-lucide-notebook-pen"
                            class="mt-0.5 size-5 shrink-0"
                        />
                        <span class="whitespace-pre-line">{{ quote.private_notes }}</span>
                    </button>
                </header>

                <UAlert
                    v-if="quote.status === 'sent'"
                    color="info"
                    variant="subtle"
                    icon="i-lucide-info"
                    title="Già inviato: se cambi un lavoro torna in bozza e va rimandato."
                />
                <UAlert
                    v-else-if="!editable"
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-lock"
                    :title="quote.status === 'accepted' ? 'Accettato: i lavori non si cambiano più. Se serve, duplicalo.' : 'Preventivo chiuso: si può solo consultare o duplicare.'"
                />

                <!-- Righe -->
                <div
                    v-if="lines.length > 0"
                    class="flex flex-col gap-3"
                >
                    <QuoteLineCard
                        v-for="line in lines"
                        :key="line.id ?? line.sort_order"
                        :line="line"
                        :editable="editable"
                        @open="openLine(line)"
                        @remove="removeLine(line)"
                    />
                    <p
                        v-if="editable"
                        class="text-center text-sm text-cemento-600 lg:hidden"
                    >
                        Scorri un lavoro verso sinistra per eliminarlo
                    </p>
                </div>

                <div
                    v-else
                    class="flex flex-col items-center gap-3 rounded-3xl bg-white px-6 py-10 text-center"
                >
                    <span class="flex size-14 items-center justify-center rounded-2xl bg-cemento-900 text-primary">
                        <UIcon
                            name="i-lucide-paint-roller"
                            class="size-7"
                        />
                    </span>
                    <p class="text-lg font-bold text-cemento-900">
                        Ancora nessun lavoro
                    </p>
                    <p class="max-w-xs text-base text-cemento-700">
                        Aggiungi le lavorazioni: i materiali e il costo li calcola l'app.
                    </p>
                </div>

                <UButton
                    v-if="editable"
                    label="Aggiungi lavorazione"
                    icon="i-lucide-plus"
                    size="xl"
                    block
                    class="min-h-14 text-lg"
                    @click="pickerOpen = true"
                />
            </div>

            <!-- Pannello desktop: riepilogo, totali, note private -->
            <aside class="hidden lg:sticky lg:top-10 lg:flex lg:flex-col lg:gap-4">
                <div class="flex flex-col gap-4 rounded-3xl bg-white p-6">
                    <h2 class="font-display text-xl font-extrabold tracking-tight text-cemento-900">
                        Riepilogo
                    </h2>
                    <QuoteTotals
                        :totals="quote"
                        variant="panel"
                    />
                </div>
                <div class="flex flex-col gap-3 rounded-3xl bg-white p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-bold text-cemento-900">
                            Note private
                        </h2>
                        <UButton
                            :label="quote.private_notes ? 'Modifica' : 'Aggiungi'"
                            color="neutral"
                            variant="link"
                            size="md"
                            @click="editNotes"
                        />
                    </div>
                    <p class="text-base whitespace-pre-line text-cemento-700">
                        {{ quote.private_notes || 'Solo per te, non vanno nel PDF.' }}
                    </p>
                </div>
            </aside>
        </div>

        <!-- Totali in basso su mobile, sopra la barra di navigazione -->
        <div
            v-if="quote"
            class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-20 border-t border-cemento-200 bg-white px-4 py-3 shadow-[0_-4px_16px_rgba(23,24,28,0.06)] lg:hidden"
        >
            <QuoteTotals
                :totals="quote"
                variant="bar"
            />
        </div>

        <JobPickerSheet
            v-if="quote"
            v-model:open="pickerOpen"
            @choose="chooseJob"
        />
        <LineSheet
            v-if="quote"
            v-model:open="lineOpen"
            :quote-id="quote.id"
            :line="editingLine"
            :job="newJob"
            @saved="lineSaved"
        />

        <UModal
            v-model:open="notesOpen"
            title="Note private"
            description="Solo per te: non vanno nel PDF."
        >
            <template #body>
                <UTextarea
                    v-model="notes"
                    :rows="5"
                    autoresize
                    autofocus
                    placeholder="Es. chiavi dal portinaio, chiamare prima di andare"
                    class="w-full"
                    size="xl"
                />
            </template>
            <template #footer>
                <div class="flex w-full flex-col gap-2 sm:flex-row-reverse">
                    <UButton
                        label="Salva"
                        icon="i-lucide-check"
                        :loading="busy"
                        class="justify-center"
                        @click="saveNotes"
                    />
                    <UButton
                        label="Annulla"
                        color="neutral"
                        variant="ghost"
                        class="justify-center"
                        @click="notesOpen = false"
                    />
                </div>
            </template>
        </UModal>

        <UModal
            v-model:open="deleteOpen"
            title="Eliminare il preventivo?"
            :description="quote ? `n. ${quote.number_label} · ${quote.customer?.name}. Non si può annullare.` : undefined"
        >
            <template #footer>
                <div class="flex w-full flex-col gap-2 sm:flex-row-reverse">
                    <UButton
                        label="Elimina"
                        icon="i-lucide-trash-2"
                        color="error"
                        :loading="busy"
                        class="justify-center"
                        @click="remove"
                    />
                    <UButton
                        label="Annulla"
                        color="neutral"
                        variant="ghost"
                        class="justify-center"
                        @click="deleteOpen = false"
                    />
                </div>
            </template>
        </UModal>
    </section>
</template>
