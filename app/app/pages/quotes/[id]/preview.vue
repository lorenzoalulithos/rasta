<script setup lang="ts">
import type { Quote } from '~/types/api'

// Anteprima PDF (docs/flows.md §2.5): generato ogni volta dall'API. Desktop: dentro la pagina;
// telefono: si apre nel lettore di sistema. Condividi con il file (Web Share API), altrimenti scarica
const route = useRoute()
const quotes = useQuotes()
const toast = useToast()
const isDesktop = useIsDesktop()

const id = computed(() => String(route.params.id))
const quote = ref<Quote | null>(null)
const url = ref<string | null>(null)
const file = ref<File | null>(null)
const failed = ref(false)
const sending = ref(false)

useHead(() => ({ title: quote.value ? `PDF n. ${quote.value.number_label} · duemani` : 'PDF · duemani' }))

const sizeLabel = computed(() => (file.value ? `${Math.max(1, Math.round(file.value.size / 1024))} KB` : ''))

async function load(): Promise<void> {
    failed.value = false

    try {
        const [detail, pdf] = await Promise.all([quotes.get(id.value), quotes.pdf(id.value)])
        quote.value = detail.data
        file.value = new File([pdf.blob], pdf.filename, { type: 'application/pdf' })
        url.value = URL.createObjectURL(file.value)
    }
    catch {
        failed.value = true
    }
}

onMounted(load)
onBeforeUnmount(() => {
    if (url.value) {
        URL.revokeObjectURL(url.value)
    }
})

function openPdf(): void {
    if (url.value) {
        window.open(url.value, '_blank')
    }
}

function download(): void {
    if (!url.value || !file.value) {
        return
    }

    const link = document.createElement('a')
    link.href = url.value
    link.download = file.value.name
    link.click()
}

async function share(): Promise<void> {
    if (!file.value || !quote.value) {
        return
    }

    const data = {
        files: [file.value],
        title: `Preventivo n. ${quote.value.number_label}`,
        text: `Preventivo n. ${quote.value.number_label} · ${quote.value.customer?.name ?? ''}`,
    }

    if (typeof navigator.canShare === 'function' && navigator.canShare(data)) {
        try {
            await navigator.share(data)
        }
        catch (error) {
            // Chiudere il menu di condivisione non è un errore
            if ((error as DOMException).name !== 'AbortError') {
                download()
            }
        }

        return
    }

    download()
    toast.add({ title: 'PDF scaricato', description: 'Allegalo alla mail o a WhatsApp dai download.', color: 'success', icon: 'i-lucide-download' })
}

async function markSent(): Promise<void> {
    sending.value = true

    try {
        await quotes.transition(id.value, 'sent')
        toast.add({ title: 'Segnato come inviato', color: 'success', icon: 'i-lucide-send' })
        await navigateTo(`/quotes/${id.value}`)
    }
    catch (error) {
        toast.add({ title: errorMessage(error), color: 'error', icon: 'i-lucide-circle-alert' })
    }
    finally {
        sending.value = false
    }
}
</script>

<template>
    <section class="flex flex-col gap-5 pb-8">
        <UButton
            :to="`/quotes/${id}`"
            label="Torna al preventivo"
            icon="i-lucide-arrow-left"
            color="neutral"
            variant="link"
            class="-mx-2.5 self-start"
        />

        <div
            v-if="failed"
            class="flex flex-col items-center gap-4 rounded-3xl bg-white px-6 py-10 text-center"
        >
            <p class="text-base text-cemento-700">
                Non sono riuscito a preparare il PDF. Controlla la connessione.
            </p>
            <UButton
                label="Riprova"
                icon="i-lucide-refresh-cw"
                @click="load"
            />
        </div>

        <div
            v-else-if="!quote || !url"
            class="flex flex-col items-center gap-4 rounded-3xl bg-white px-6 py-14 text-center"
            aria-busy="true"
        >
            <UIcon
                name="i-lucide-loader-circle"
                class="size-10 animate-spin text-cemento-500"
            />
            <p class="text-base text-cemento-700">
                Preparo il PDF…
            </p>
        </div>

        <div
            v-else
            class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(18rem,1fr)] lg:items-start"
        >
            <!-- Il PDF -->
            <iframe
                v-if="isDesktop"
                :src="url"
                :title="`Preventivo n. ${quote.number_label}`"
                class="h-[calc(100dvh-10rem)] w-full rounded-2xl bg-white"
            />
            <button
                v-else
                type="button"
                class="flex min-w-0 items-center gap-4 rounded-3xl bg-white p-5 text-left"
                @click="openPdf"
            >
                <span class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-cemento-900 text-primary">
                    <UIcon
                        name="i-lucide-file-text"
                        class="size-8"
                    />
                </span>
                <span class="flex min-w-0 flex-col">
                    <span class="truncate text-lg font-bold text-cemento-900">{{ file?.name }}</span>
                    <span class="text-base text-cemento-700">{{ sizeLabel }} · tocca per aprirlo</span>
                </span>
            </button>

            <!-- Azioni -->
            <div class="flex flex-col gap-3 lg:sticky lg:top-10 lg:rounded-3xl lg:bg-white lg:p-6">
                <div class="flex flex-col gap-1">
                    <span class="text-base font-semibold text-cemento-700">n. {{ quote.number_label }}</span>
                    <span class="font-display text-2xl leading-tight font-extrabold tracking-tight text-cemento-900">{{ quote.customer?.name }}</span>
                    <span class="text-2xl font-extrabold text-cemento-900 tabular-nums">{{ formatEuro(quote.total) }}</span>
                </div>

                <UButton
                    label="Condividi"
                    icon="i-lucide-share-2"
                    size="xl"
                    block
                    class="min-h-14 text-lg"
                    @click="share"
                />
                <p class="text-center text-sm text-cemento-600">
                    WhatsApp, email o dove vuoi. Se non si può, lo scarica.
                </p>

                <UButton
                    v-if="quote.status === 'draft'"
                    label="Segna come inviato"
                    icon="i-lucide-send"
                    color="neutral"
                    size="xl"
                    block
                    :loading="sending"
                    class="min-h-14 text-lg"
                    @click="markSent"
                />
                <UButton
                    v-if="isDesktop"
                    label="Scarica"
                    icon="i-lucide-download"
                    color="neutral"
                    variant="outline"
                    block
                    @click="download"
                />
            </div>
        </div>
    </section>
</template>
