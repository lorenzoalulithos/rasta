<script setup lang="ts">
import type { Customer, Site } from '~/types/api'

// Nuovo preventivo, step 1: cliente e cantiere (docs/flows.md §2.2)
useHead({ title: 'Nuovo preventivo · duemani' })

const customersApi = useCustomers()
const quotes = useQuotes()
const toast = useToast()

const customer = ref<Customer | null>(null)
const site = ref<Site | null>(null)
const sites = ref<Site[]>([])
const loadingSites = ref(false)
const customerSheet = ref(false)
const siteSheet = ref(false)
const creating = ref(false)

async function chooseCustomer(chosen: Customer): Promise<void> {
    customer.value = chosen
    site.value = null
    sites.value = []
    loadingSites.value = true

    try {
        sites.value = (await customersApi.sites(chosen.id)).data

        // Un solo cantiere: è già scelto. Nessuno: si apre subito "Nuovo cantiere"
        if (sites.value.length === 1) {
            site.value = sites.value[0] ?? null
        }
        else {
            siteSheet.value = true
        }
    }
    catch (error) {
        toast.add({ title: errorMessage(error), color: 'error', icon: 'i-lucide-circle-alert' })
    }
    finally {
        loadingSites.value = false
    }
}

function chooseSite(chosen: Site): void {
    if (!sites.value.some(item => item.id === chosen.id)) {
        sites.value = [...sites.value, chosen]
    }

    site.value = chosen
}

async function start(): Promise<void> {
    if (site.value === null) {
        return
    }

    creating.value = true

    try {
        const response = await quotes.create(site.value.id)
        await navigateTo(`/quotes/${response.data.id}`, { replace: true })
    }
    catch (error) {
        toast.add({ title: errorMessage(error, 'Non sono riuscito a creare il preventivo. Riprova.'), color: 'error', icon: 'i-lucide-circle-alert' })
        creating.value = false
    }
}
</script>

<template>
    <section class="mx-auto flex w-full max-w-xl flex-col gap-6 pb-28 lg:pb-0">
        <div class="flex flex-col gap-2">
            <UButton
                to="/quotes"
                label="Preventivi"
                icon="i-lucide-arrow-left"
                color="neutral"
                variant="link"
                class="-mx-2.5 self-start"
            />
            <h1 class="font-display text-4xl font-extrabold tracking-tight text-cemento-900">
                Nuovo preventivo
            </h1>
            <p class="text-base text-cemento-700">
                Per chi è il lavoro e dove si fa. Poi aggiungi le lavorazioni.
            </p>
        </div>

        <!-- 1. Cliente -->
        <div class="flex flex-col gap-2">
            <span class="text-base font-bold text-cemento-900">1 · Cliente</span>
            <button
                type="button"
                class="flex min-h-18 w-full items-center gap-4 rounded-2xl border-2 bg-white px-4 py-3 text-left transition-colors focus-visible:outline-3 focus-visible:outline-primary"
                :class="customer ? 'border-transparent' : 'border-dashed border-cemento-300 hover:border-cemento-500'"
                @click="customerSheet = true"
            >
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-cemento-900 text-primary">
                    <UIcon
                        name="i-lucide-user"
                        class="size-6"
                    />
                </span>
                <span class="flex min-w-0 flex-1 flex-col">
                    <span
                        class="truncate text-lg font-bold"
                        :class="customer ? 'text-cemento-900' : 'text-cemento-700'"
                    >{{ customer?.name ?? 'Scegli o crea il cliente' }}</span>
                    <span
                        v-if="customer"
                        class="text-sm font-semibold text-cemento-700"
                    >Tocca per cambiare</span>
                </span>
                <UIcon
                    :name="customer ? 'i-lucide-pencil' : 'i-lucide-chevron-right'"
                    class="size-5 shrink-0 text-cemento-500"
                />
            </button>
        </div>

        <!-- 2. Cantiere, dopo il cliente -->
        <div
            v-if="customer"
            class="flex flex-col gap-2"
        >
            <span class="text-base font-bold text-cemento-900">2 · Cantiere</span>
            <button
                type="button"
                class="flex min-h-18 w-full items-center gap-4 rounded-2xl border-2 bg-white px-4 py-3 text-left transition-colors focus-visible:outline-3 focus-visible:outline-primary disabled:opacity-60"
                :class="site ? 'border-transparent' : 'border-dashed border-cemento-300 hover:border-cemento-500'"
                :disabled="loadingSites"
                @click="siteSheet = true"
            >
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-cemento-900 text-primary">
                    <UIcon
                        :name="loadingSites ? 'i-lucide-loader-circle' : 'i-lucide-map-pin'"
                        class="size-6"
                        :class="loadingSites ? 'animate-spin' : ''"
                    />
                </span>
                <span class="flex min-w-0 flex-1 flex-col">
                    <span
                        class="truncate text-lg font-bold"
                        :class="site ? 'text-cemento-900' : 'text-cemento-700'"
                    >{{ site?.name ?? 'Scegli o crea il cantiere' }}</span>
                    <span
                        v-if="site"
                        class="truncate text-sm font-semibold text-cemento-700"
                    >{{ site.address ? [site.address, site.city].filter(Boolean).join(', ') : 'Indirizzo del cliente' }}</span>
                </span>
                <UIcon
                    :name="site ? 'i-lucide-pencil' : 'i-lucide-chevron-right'"
                    class="size-5 shrink-0 text-cemento-500"
                />
            </button>
        </div>

        <!-- Continua: in basso su mobile, sotto i campi su desktop -->
        <div class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-20 bg-cemento-100/95 px-4 py-3 backdrop-blur lg:static lg:bg-transparent lg:p-0">
            <UButton
                label="Continua"
                trailing-icon="i-lucide-arrow-right"
                size="xl"
                block
                :disabled="site === null"
                :loading="creating"
                class="mx-auto min-h-14 max-w-xl text-lg"
                @click="start"
            />
        </div>

        <CustomerSheet
            v-model:open="customerSheet"
            @select="chooseCustomer"
        />
        <SiteSheet
            v-if="customer"
            v-model:open="siteSheet"
            :customer="customer"
            :sites="sites"
            @select="chooseSite"
        />
    </section>
</template>
