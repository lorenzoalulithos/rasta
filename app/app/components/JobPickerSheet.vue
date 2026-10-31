<script setup lang="ts">
import * as z from 'zod'
import type { Form, FormSubmitEvent } from '@nuxt/ui'
import type { Job, JobUnit } from '~/types/api'

// "+ Aggiungi lavorazione" (docs/flows.md §2.3): lavorazioni attive, "+ Nuova lavorazione" e "Riga libera".
// Scelta la lavorazione si apre la riga (§2.4, LineSheet) per la quantità: `choose` con la lavorazione, o null per la riga libera
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ choose: [job: Job | null] }>()

const catalog = useCatalog()
const toast = useToast()

type Step = 'list' | 'newJob'

const step = ref<Step>('list')
const search = ref('')
const jobs = ref<Job[]>([])
const loading = ref(false)

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

function choose(job: Job | null): void {
    open.value = false
    emit('choose', job)
}

// ── Nuova lavorazione: entra nel catalogo e si usa subito ──
const jobForm = useTemplateRef<Form<JobSchema>>('jobForm')
const jobSchema = z.object({
    name: z.string().trim().min(1, 'Scrivi il nome della lavorazione').max(255),
    unit: z.enum(['sqm', 'hour', 'pc', 'pair', 'lm']),
    unit_price: z.string().trim().min(1, 'Scrivi il prezzo').refine(value => (parseNumber(value) ?? -1) >= 0, 'Scrivi un numero, es. 4,50'),
    default_coats: z.number().optional(),
})
type JobSchema = z.output<typeof jobSchema>
const jobState = reactive<{ name: string, unit: JobUnit, unit_price: string, default_coats: number | undefined }>({ name: '', unit: 'sqm', unit_price: '', default_coats: 2 })
const saving = ref(false)

function startNewJob(): void {
    Object.assign(jobState, { name: search.value.trim(), unit: 'sqm', unit_price: '', default_coats: 2 })
    step.value = 'newJob'
}

async function saveJob(event: FormSubmitEvent<JobSchema>): Promise<void> {
    saving.value = true

    try {
        const response = await catalog.createJob({
            name: event.data.name,
            unit: event.data.unit,
            unit_price: parseNumber(event.data.unit_price) ?? 0,
            default_coats: event.data.unit === 'sqm' ? event.data.default_coats ?? null : null,
        })

        choose(response.data)
    }
    catch (error) {
        const errors = validationErrors(error)

        if (errors.length > 0) {
            jobForm.value?.setErrors(errors)
        }
        else {
            toast.add({ title: errorMessage(error), color: 'error', icon: 'i-lucide-circle-alert' })
        }
    }
    finally {
        saving.value = false
    }
}
</script>

<template>
    <AppSheet
        v-model:open="open"
        :title="step === 'list' ? 'Aggiungi lavorazione' : 'Nuova lavorazione'"
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
                        @click="choose(item)"
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
                @click="choose(null)"
            />
        </div>

        <!-- Nuova lavorazione -->
        <UForm
            v-else
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
    </AppSheet>
</template>
