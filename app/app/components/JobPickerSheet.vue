<script setup lang="ts">
import * as z from 'zod'
import type { Form, FormSubmitEvent } from '@nuxt/ui'
import type { Job, JobUnit } from '~/types/api'

// "+ Aggiungi lavorazione" (docs/flows.md §2.3): lavorazioni attive, "+ Nuova lavorazione" e "Riga libera".
// Scelta la lavorazione si scrive la quantità e la riga si aggiunge; la riga completa (prezzo, materiali) è il bottom sheet di F4.5.
const props = defineProps<{ quoteId: string }>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ added: [] }>()

const catalog = useCatalog()
const quotes = useQuotes()
const toast = useToast()

type Step = 'list' | 'newJob' | 'quantity' | 'free'

const step = ref<Step>('list')
const search = ref('')
const jobs = ref<Job[]>([])
const loading = ref(false)
const job = ref<Job | null>(null)

const titles: Record<Step, string> = {
    list: 'Aggiungi lavorazione',
    newJob: 'Nuova lavorazione',
    quantity: 'Quanto?',
    free: 'Riga libera',
}

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase()

    return term === '' ? jobs.value : jobs.value.filter(item => item.name.toLowerCase().includes(term))
})

const unitItems = (Object.keys(JOB_UNITS) as JobUnit[]).map(value => ({ value, label: JOB_UNITS[value].choice }))
const coatItems = [1, 2, 3].map(value => ({ value, label: value === 1 ? '1 mano' : `${value} mani` }))

watch(open, async (value) => {
    if (!value) {
        return
    }

    step.value = 'list'
    search.value = ''
    loading.value = true

    try {
        jobs.value = (await catalog.jobs({ active: 1, per_page: 100 })).data
    }
    catch (error) {
        toast.add({ title: errorMessage(error), color: 'error', icon: 'i-lucide-circle-alert' })
    }
    finally {
        loading.value = false
    }
})

const amountSchema = z.string().trim().min(1, 'Scrivi un numero').refine(value => (parseNumber(value) ?? -1) >= 0, 'Scrivi un numero, es. 4,50')
const quantitySchema = z.string().trim().min(1, 'Scrivi la quantità').refine(value => (parseNumber(value) ?? 0) > 0, 'Scrivi un numero più grande di zero, es. 181')

// ── Quantità della lavorazione scelta ──
const quantityForm = useTemplateRef<Form<QuantitySchema>>('quantityForm')
const quantityFormSchema = z.object({ quantity: quantitySchema, coats: z.number().optional() })
type QuantitySchema = z.output<typeof quantityFormSchema>
const quantityState = reactive<{ quantity: string, coats: number | undefined }>({ quantity: '', coats: undefined })

function chooseJob(chosen: Job): void {
    job.value = chosen
    quantityState.quantity = ''
    quantityState.coats = chosen.default_coats ?? undefined
    step.value = 'quantity'
}

// ── Nuova lavorazione: entra nel catalogo e si usa subito ──
const jobForm = useTemplateRef<Form<JobSchema>>('jobForm')
const jobSchema = z.object({
    name: z.string().trim().min(1, 'Scrivi il nome della lavorazione').max(255),
    unit: z.enum(['sqm', 'hour', 'pc', 'pair', 'lm']),
    unit_price: amountSchema,
    default_coats: z.number().optional(),
})
type JobSchema = z.output<typeof jobSchema>
const jobState = reactive<{ name: string, unit: JobUnit, unit_price: string, default_coats: number | undefined }>({ name: '', unit: 'sqm', unit_price: '', default_coats: 2 })

function startNewJob(): void {
    Object.assign(jobState, { name: search.value.trim(), unit: 'sqm', unit_price: '', default_coats: 2 })
    step.value = 'newJob'
}

// ── Riga libera: descrizione e prezzo scritti a mano ──
const freeForm = useTemplateRef<Form<FreeSchema>>('freeForm')
const freeSchema = z.object({
    description: z.string().trim().min(1, 'Scrivi cosa fai').max(255),
    quantity: quantitySchema,
    unit_price: amountSchema,
})
type FreeSchema = z.output<typeof freeSchema>
const freeState = reactive({ description: '', quantity: '1', unit_price: '' })

function startFree(): void {
    Object.assign(freeState, { description: search.value.trim(), quantity: '1', unit_price: '' })
    step.value = 'free'
}

const saving = ref(false)

async function submit<T>(form: Form<T> | null, action: () => Promise<void>): Promise<void> {
    saving.value = true

    try {
        await action()
    }
    catch (error) {
        const errors = validationErrors(error)

        if (errors.length > 0 && form) {
            form.setErrors(errors)
        }
        else {
            toast.add({ title: errorMessage(error), color: 'error', icon: 'i-lucide-circle-alert' })
        }
    }
    finally {
        saving.value = false
    }
}

function added(): void {
    emit('added')
    open.value = false
}

function saveJob(event: FormSubmitEvent<JobSchema>): Promise<void> {
    return submit(jobForm.value, async () => {
        const response = await catalog.createJob({
            name: event.data.name,
            unit: event.data.unit,
            unit_price: parseNumber(event.data.unit_price) ?? 0,
            default_coats: event.data.unit === 'sqm' ? event.data.default_coats ?? null : null,
        })

        jobs.value = [...jobs.value, response.data]
        chooseJob(response.data)
    })
}

function addJobLine(event: FormSubmitEvent<QuantitySchema>): Promise<void> {
    return submit(quantityForm.value, async () => {
        await quotes.addLine(props.quoteId, {
            job_id: job.value?.id,
            quantity: parseNumber(event.data.quantity) ?? 0,
            coats: job.value?.default_coats ? event.data.coats ?? null : null,
        })
        added()
    })
}

function addFreeLine(event: FormSubmitEvent<FreeSchema>): Promise<void> {
    return submit(freeForm.value, async () => {
        await quotes.addLine(props.quoteId, {
            description: event.data.description,
            quantity: parseNumber(event.data.quantity) ?? 1,
            unit_price: parseNumber(event.data.unit_price) ?? 0,
        })
        added()
    })
}
</script>

<template>
    <AppSheet
        v-model:open="open"
        :title="titles[step]"
        :description="step === 'quantity' ? job?.name : undefined"
    >
        <!-- Lista lavorazioni -->
        <div
            v-if="step === 'list'"
            class="flex flex-col gap-3"
        >
            <UInput
                v-model="search"
                type="search"
                icon="i-lucide-search"
                placeholder="Cerca lavorazione"
                aria-label="Cerca lavorazione"
                class="w-full"
            />

            <UButton
                label="Nuova lavorazione"
                icon="i-lucide-plus"
                block
                class="min-h-14 justify-start text-base"
                @click="startNewJob"
            />

            <div
                v-if="loading"
                class="flex flex-col gap-2"
            >
                <USkeleton
                    v-for="n in 5"
                    :key="n"
                    class="h-14 rounded-xl"
                />
            </div>

            <ul
                v-else
                class="flex flex-col"
            >
                <li
                    v-for="item in filtered"
                    :key="item.id"
                >
                    <button
                        type="button"
                        class="flex min-h-16 w-full items-center justify-between gap-3 border-b border-cemento-200 py-3 text-left"
                        @click="chooseJob(item)"
                    >
                        <span class="min-w-0 text-lg font-bold text-cemento-900">{{ item.name }}</span>
                        <span class="shrink-0 text-base font-semibold text-cemento-700 tabular-nums">{{ formatEuro(item.unit_price) }}/{{ JOB_UNITS[item.unit].per }}</span>
                    </button>
                </li>
            </ul>

            <p
                v-if="!loading && filtered.length === 0"
                class="py-2 text-center text-base text-cemento-700"
            >
                Nessuna lavorazione "{{ search.trim() }}": creala qui sopra o usa una riga libera.
            </p>

            <UButton
                label="Riga libera, fuori catalogo"
                icon="i-lucide-pencil-line"
                color="neutral"
                variant="outline"
                block
                class="mt-1 min-h-14 justify-start text-base"
                @click="startFree"
            />
        </div>

        <!-- Nuova lavorazione -->
        <UForm
            v-else-if="step === 'newJob'"
            ref="jobForm"
            :schema="jobSchema"
            :state="jobState"
            :validate-on="['input']"
            novalidate
            class="flex flex-col gap-5"
            @submit="saveJob"
        >
            <UFormField
                label="Nome"
                name="name"
                required
                size="xl"
            >
                <UInput
                    v-model="jobState.name"
                    autofocus
                    placeholder="Es. Imbiancatura interna"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                label="Si conta a"
                name="unit"
                size="xl"
            >
                <AppSegmented
                    v-model="jobState.unit"
                    :items="unitItems"
                    label="Unità"
                />
            </UFormField>

            <UFormField
                label="Prezzo"
                name="unit_price"
                required
                size="xl"
                :hint="`€/${JOB_UNITS[jobState.unit].per}`"
            >
                <UInput
                    v-model="jobState.unit_price"
                    inputmode="decimal"
                    placeholder="0,00"
                    class="w-full"
                >
                    <template #trailing>
                        <span class="text-base font-bold text-cemento-700">€</span>
                    </template>
                </UInput>
            </UFormField>

            <UFormField
                v-if="jobState.unit === 'sqm'"
                label="Mani di solito"
                name="default_coats"
                size="xl"
            >
                <AppSegmented
                    v-model="jobState.default_coats"
                    :items="coatItems"
                    label="Mani"
                />
            </UFormField>

            <div class="mt-1 flex flex-col gap-2">
                <UButton
                    type="submit"
                    label="Salva e usa"
                    icon="i-lucide-check"
                    size="xl"
                    block
                    :loading="saving"
                    class="min-h-14 text-lg"
                />
                <UButton
                    label="Torna alla lista"
                    color="neutral"
                    variant="ghost"
                    block
                    @click="step = 'list'"
                />
            </div>
        </UForm>

        <!-- Quantità -->
        <UForm
            v-else-if="step === 'quantity' && job"
            ref="quantityForm"
            :schema="quantityFormSchema"
            :state="quantityState"
            :validate-on="['input']"
            novalidate
            class="flex flex-col gap-5"
            @submit="addJobLine"
        >
            <UFormField
                label="Quantità"
                name="quantity"
                required
                size="xl"
                :help="`${formatEuro(job.unit_price)}/${JOB_UNITS[job.unit].per}`"
            >
                <UInput
                    v-model="quantityState.quantity"
                    inputmode="decimal"
                    autofocus
                    placeholder="0"
                    class="w-full"
                    :ui="{ base: 'h-16 text-3xl font-extrabold tabular-nums' }"
                >
                    <template #trailing>
                        <span class="text-xl font-bold text-cemento-700">{{ JOB_UNITS[job.unit].short }}</span>
                    </template>
                </UInput>
            </UFormField>

            <UFormField
                v-if="job.default_coats"
                label="Mani"
                name="coats"
                size="xl"
            >
                <AppSegmented
                    v-model="quantityState.coats"
                    :items="coatItems"
                    label="Mani"
                />
            </UFormField>

            <div class="mt-1 flex flex-col gap-2">
                <UButton
                    type="submit"
                    label="Aggiungi al preventivo"
                    icon="i-lucide-plus"
                    size="xl"
                    block
                    :loading="saving"
                    class="min-h-14 text-lg"
                />
                <UButton
                    label="Scegli un'altra lavorazione"
                    color="neutral"
                    variant="ghost"
                    block
                    @click="step = 'list'"
                />
            </div>
        </UForm>

        <!-- Riga libera -->
        <UForm
            v-else-if="step === 'free'"
            ref="freeForm"
            :schema="freeSchema"
            :state="freeState"
            :validate-on="['input']"
            novalidate
            class="flex flex-col gap-5"
            @submit="addFreeLine"
        >
            <UFormField
                label="Descrizione"
                name="description"
                required
                size="xl"
                help="Come la leggerà il cliente sul PDF"
            >
                <UInput
                    v-model="freeState.description"
                    autofocus
                    placeholder="Es. Trattamento portone in legno"
                    class="w-full"
                />
            </UFormField>

            <div class="grid grid-cols-2 gap-3">
                <UFormField
                    label="Quantità"
                    name="quantity"
                    required
                    size="xl"
                >
                    <UInput
                        v-model="freeState.quantity"
                        inputmode="decimal"
                        class="w-full"
                    />
                </UFormField>
                <UFormField
                    label="Prezzo"
                    name="unit_price"
                    required
                    size="xl"
                >
                    <UInput
                        v-model="freeState.unit_price"
                        inputmode="decimal"
                        placeholder="0,00"
                        class="w-full"
                    >
                        <template #trailing>
                            <span class="text-base font-bold text-cemento-700">€</span>
                        </template>
                    </UInput>
                </UFormField>
            </div>

            <div class="mt-1 flex flex-col gap-2">
                <UButton
                    type="submit"
                    label="Aggiungi al preventivo"
                    icon="i-lucide-plus"
                    size="xl"
                    block
                    :loading="saving"
                    class="min-h-14 text-lg"
                />
                <UButton
                    label="Torna alla lista"
                    color="neutral"
                    variant="ghost"
                    block
                    @click="step = 'list'"
                />
            </div>
        </UForm>
    </AppSheet>
</template>
