<script setup lang="ts">
import type { Quote, QuoteStatus } from '~/types/api'

// Lista preventivi (docs/flows.md §2.1)
useHead({ title: 'Preventivi · duemani' })

const quotes = useQuotes()

const filters: { value: QuoteStatus | null, label: string }[] = [
    { value: null, label: 'Tutti' },
    { value: 'draft', label: QUOTE_STATUSES.draft.filter },
    { value: 'sent', label: QUOTE_STATUSES.sent.filter },
    { value: 'accepted', label: QUOTE_STATUSES.accepted.filter },
    { value: 'completed', label: QUOTE_STATUSES.completed.filter },
    { value: 'rejected', label: QUOTE_STATUSES.rejected.filter },
]

const search = ref('')
const status = ref<QuoteStatus | null>(null)
const items = ref<Quote[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const loading = ref(true)
const loadingMore = ref(false)
const failed = ref(false)

const filtering = computed(() => search.value.trim() !== '' || status.value !== null)

// Scarta le risposte arrivate dopo una ricerca più recente
let requestId = 0

async function load(more = false): Promise<void> {
    const id = ++requestId
    failed.value = false

    if (more) {
        loadingMore.value = true
    }
    else {
        loading.value = true
    }

    try {
        const response = await quotes.list({
            page: more ? page.value + 1 : 1,
            search: search.value.trim() || undefined,
            status: status.value ?? undefined,
        })

        if (id !== requestId) {
            return
        }

        items.value = more ? [...items.value, ...response.data] : response.data
        page.value = response.meta.current_page
        lastPage.value = response.meta.last_page
        total.value = response.meta.total
    }
    catch {
        if (id === requestId) {
            failed.value = true
        }
    }
    finally {
        if (id === requestId) {
            loading.value = false
            loadingMore.value = false
        }
    }
}

let debounce: ReturnType<typeof setTimeout> | undefined

watch(search, () => {
    clearTimeout(debounce)
    debounce = setTimeout(() => load(), 300)
})
watch(status, () => load())

onMounted(() => load())
onBeforeUnmount(() => clearTimeout(debounce))
</script>

<template>
    <section class="flex flex-col gap-5 pb-20 lg:pb-24">
        <h1 class="font-display text-4xl font-extrabold tracking-tight text-cemento-900">
            Preventivi
        </h1>

        <UInput
            v-model="search"
            type="search"
            icon="i-lucide-search"
            placeholder="Cerca cliente, cantiere o numero"
            aria-label="Cerca preventivi"
            class="w-full"
            :ui="{ base: 'bg-white' }"
        />

        <div
            class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:flex-wrap lg:px-0"
            role="group"
            aria-label="Filtra per stato"
        >
            <UButton
                v-for="filter in filters"
                :key="filter.label"
                :label="filter.label"
                size="lg"
                color="neutral"
                :variant="status === filter.value ? 'solid' : 'outline'"
                class="shrink-0 rounded-full"
                :class="status === filter.value ? 'bg-cemento-900 text-white' : 'bg-white'"
                :aria-pressed="status === filter.value"
                @click="status = filter.value"
            />
        </div>

        <!-- Caricamento -->
        <div
            v-if="loading"
            class="flex flex-col gap-3"
            aria-busy="true"
        >
            <USkeleton
                v-for="n in 4"
                :key="n"
                class="h-24 rounded-2xl bg-white"
            />
        </div>

        <!-- Errore -->
        <div
            v-else-if="failed"
            class="flex flex-col items-center gap-4 rounded-3xl bg-white px-6 py-10 text-center"
        >
            <p class="text-base text-cemento-700">
                Non riesco a caricare i preventivi. Controlla la connessione.
            </p>
            <UButton
                label="Riprova"
                icon="i-lucide-refresh-cw"
                @click="load()"
            />
        </div>

        <!-- Vuoto -->
        <div
            v-else-if="items.length === 0"
            class="flex flex-col items-center gap-4 rounded-3xl bg-white px-6 py-12 text-center"
        >
            <span class="flex size-14 items-center justify-center rounded-2xl bg-cemento-900 text-primary">
                <UIcon
                    :name="filtering ? 'i-lucide-search-x' : 'i-lucide-file-text'"
                    class="size-7"
                />
            </span>
            <template v-if="filtering">
                <p class="text-lg font-bold text-cemento-900">
                    Nessun preventivo trovato
                </p>
                <UButton
                    label="Mostra tutti"
                    color="neutral"
                    variant="outline"
                    @click="search = ''; status = null"
                />
            </template>
            <template v-else>
                <p class="text-lg font-bold text-cemento-900">
                    Ancora nessun preventivo
                </p>
                <p class="max-w-xs text-base text-cemento-700">
                    Scegli il cliente, aggiungi i lavori: i materiali li calcola l'app.
                </p>
                <UButton
                    to="/quotes/new"
                    label="Fai il primo preventivo"
                    icon="i-lucide-plus"
                />
            </template>
        </div>

        <!-- Lista -->
        <template v-else>
            <p class="text-sm font-semibold text-cemento-700">
                {{ total === 1 ? '1 preventivo' : `${total} preventivi` }}
            </p>
            <div class="grid gap-3 lg:grid-cols-2">
                <QuoteCard
                    v-for="quote in items"
                    :key="quote.id"
                    :quote="quote"
                />
            </div>
            <UButton
                v-if="page < lastPage"
                label="Mostra altri"
                color="neutral"
                variant="outline"
                block
                :loading="loadingMore"
                class="bg-white"
                @click="load(true)"
            />
        </template>

        <AppFab />
    </section>
</template>
