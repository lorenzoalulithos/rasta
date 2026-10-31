<script setup lang="ts">
import * as z from 'zod'
import type { Form } from '@nuxt/ui'
import type { Job, Material, PreviewLineInput, QuoteLine, QuoteLineMaterial, QuoteLineMaterialInput } from '~/types/api'

// Riga del preventivo (docs/flows.md §2.4): quantità, mani, prezzo, materiali con il calcolo live
// via preview-calculate (debounce 300 ms). Nuova riga da una lavorazione (o riga libera) oppure modifica di una esistente
const props = defineProps<{
    quoteId: string
    /** Riga da modificare */
    line?: QuoteLine | null
    /** Nuova riga: la lavorazione scelta, null per la riga libera */
    job?: Job | null
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [message: string] }>()

const quotes = useQuotes()
const catalog = useCatalog()
const toast = useToast()

const DEBOUNCE_MS = 300

const editing = computed(() => !!props.line)
const isFree = computed(() => (editing.value ? props.line?.job_id === null : !props.job))
const unit = computed(() => props.line?.unit ?? props.job?.unit ?? 'pc')
const showCoats = computed(() => (editing.value ? props.line?.coats !== null : !!props.job?.default_coats))

const coatItems = [1, 2, 3].map(value => ({ value, label: value === 1 ? '1 mano' : `${value} mani` }))

// ── Campi ──
const state = reactive({ description: '', quantity: '', coats: undefined as number | undefined, unit_price: '' })

/** Spunte cambiate e materiali aggiunti: si mandano solo questi ("corregge e aggiunge", docs/api.md) */
const changes = ref<Record<string, QuoteLineMaterialInput>>({})

const preview = ref<QuoteLine | null>(null)
const calculating = ref(false)
const saving = ref(false)

type View = 'form' | 'materials' | 'perUnit'
const view = ref<View>('form')

watch(open, (value) => {
    if (!value) {
        return
    }

    view.value = 'form'
    changes.value = {}
    preview.value = props.line ?? null

    if (props.line) {
        Object.assign(state, {
            description: props.line.description,
            quantity: formatQuantity(props.line.quantity),
            coats: props.line.coats ?? undefined,
            unit_price: formatMoneyInput(props.line.unit_price),
        })
    }
    else {
        Object.assign(state, {
            description: props.job?.name ?? '',
            quantity: isFree.value ? '1' : '',
            coats: props.job?.default_coats ?? undefined,
            unit_price: props.job ? formatMoneyInput(props.job.unit_price) : '',
        })
    }
})

const quantity = computed(() => parseNumber(state.quantity))
const unitPrice = computed(() => parseNumber(state.unit_price))
const valid = computed(() => (quantity.value ?? 0) > 0 && (unitPrice.value ?? -1) >= 0)

function payload(): PreviewLineInput {
    return {
        ...(editing.value ? {} : { job_id: props.job?.id ?? null }),
        description: state.description.trim() || undefined,
        ...(isFree.value ? { unit: unit.value } : {}),
        quantity: quantity.value ?? 0,
        coats: showCoats.value ? state.coats ?? null : null,
        unit_price: unitPrice.value,
        ...(isFree.value ? {} : { materials: Object.values(changes.value) }),
    }
}

// ── Calcolo live ──
let debounce: ReturnType<typeof setTimeout> | undefined
let requestId = 0

async function calculate(): Promise<void> {
    if (!valid.value) {
        return
    }

    const id = ++requestId
    calculating.value = true

    try {
        const response = await quotes.preview(props.quoteId, [{ ...payload(), ...(props.line?.id ? { id: props.line.id } : {}) }])

        if (id === requestId) {
            preview.value = response.data.lines[0] ?? null
        }
    }
    catch {
        // Il calcolo live è un'anteprima: se non arriva resta l'ultimo, gli errori veri li dà il salvataggio
    }
    finally {
        if (id === requestId) {
            calculating.value = false
        }
    }
}

watch(() => [state.quantity, state.coats, state.unit_price, changes.value], () => {
    if (!open.value) {
        return
    }

    clearTimeout(debounce)
    debounce = setTimeout(calculate, DEBOUNCE_MS)
}, { deep: true })

onBeforeUnmount(() => clearTimeout(debounce))

const amount = computed(() => (valid.value ? (preview.value?.amount ?? (quantity.value ?? 0) * (unitPrice.value ?? 0)) : null))

// ── Materiali ──
const ROLE_ORDER = { primary: 0, secondary: 1, checklist: 2 } as const

/** Dal calcolo; senza quantità si mostrano i suggeriti della lavorazione, senza numeri. */
const materials = computed<QuoteLineMaterial[]>(() => {
    const fromPreview = valid.value ? preview.value?.materials : undefined
    const list = fromPreview ?? suggestedFromJob()

    return [...list].sort((one, other) => ROLE_ORDER[one.role] - ROLE_ORDER[other.role])
})

function suggestedFromJob(): QuoteLineMaterial[] {
    if (props.line?.materials) {
        return props.line.materials
    }

    return (props.job?.materials ?? [])
        .filter(item => item.role !== 'checklist' && item.consumption_mode !== 'none' && item.material)
        .map(item => ({
            id: null,
            material_id: item.material_id,
            material: { name: item.material?.name ?? '', package_label: item.material?.package_label ?? '', base_unit: item.material?.base_unit ?? 'lt' },
            role: item.role,
            consumption_mode: item.consumption_mode,
            consumption_per_unit: item.consumption_per_unit,
            apply_coats: item.apply_coats,
            selected: changes.value[item.material_id]?.selected ?? item.selected_by_default,
            coverage_used: null,
            required_quantity: 0,
            required_packages: 0,
            unit_cost: 0,
            estimated_cost: 0,
        }))
}

function toggle(material: QuoteLineMaterial, selected: boolean): void {
    // Un materiale aggiunto qui e poi tolto non va mandato affatto
    if (!selected && changes.value[material.material_id]?.consumption_mode) {
        const { [material.material_id]: _removed, ...rest } = changes.value
        changes.value = rest

        return
    }

    changes.value = {
        ...changes.value,
        [material.material_id]: { ...changes.value[material.material_id], material_id: material.material_id, selected },
    }
}

function requirement(material: QuoteLineMaterial): string | null {
    if (!material.selected || !valid.value || material.required_packages === 0) {
        return null
    }

    const base = material.material?.base_unit ?? ''

    return `${formatQuantity(Math.round(material.required_quantity * 10) / 10)} ${base} → ${formatQuantity(material.required_packages)} × ${material.material?.package_label} · ${formatEuro(material.estimated_cost)}`
}

const materialsCost = computed(() => materials.value.filter(item => item.selected).reduce((sum, item) => sum + item.estimated_cost, 0))

// ── "+ Altro materiale" ──
const catalogMaterials = ref<Material[]>([])
const materialSearch = ref('')
const loadingMaterials = ref(false)
const perUnitMaterial = ref<Material | null>(null)
const perUnit = ref('')

async function openMaterials(): Promise<void> {
    view.value = 'materials'
    materialSearch.value = ''

    if (catalogMaterials.value.length > 0) {
        return
    }

    loadingMaterials.value = true

    try {
        catalogMaterials.value = (await catalog.materials({ kind: 'consumable_quantity', per_page: 100 })).data
    }
    catch (error) {
        toast.add({ title: errorMessage(error), color: 'error', icon: 'i-lucide-circle-alert' })
    }
    finally {
        loadingMaterials.value = false
    }
}

const availableMaterials = computed(() => {
    const present = new Set(materials.value.map(item => item.material_id))
    const term = materialSearch.value.trim().toLowerCase()

    return catalogMaterials.value.filter(item => !present.has(item.id) && (term === '' || item.name.toLowerCase().includes(term)))
})

function pickMaterial(material: Material): void {
    // Con la resa si calcola dai mq; senza, serve il consumo per unità (docs/flows.md §2.4)
    if (material.coverage_min !== null || material.coverage_max !== null) {
        addMaterial({ material_id: material.id, selected: true, consumption_mode: 'coverage' })

        return
    }

    perUnitMaterial.value = material
    perUnit.value = ''
    view.value = 'perUnit'
}

function confirmPerUnit(): void {
    const value = parseNumber(perUnit.value)

    if (!perUnitMaterial.value || value === null || value <= 0) {
        return
    }

    addMaterial({ material_id: perUnitMaterial.value.id, selected: true, consumption_mode: 'per_unit', consumption_per_unit: value })
}

function addMaterial(input: QuoteLineMaterialInput): void {
    changes.value = { ...changes.value, [input.material_id]: input }
    view.value = 'form'

    // Senza quantità non c'è calcolo: il materiale compare appena la si scrive
    if (!valid.value) {
        toast.add({ title: 'Materiale aggiunto: scrivi la quantità per vedere quanto ne serve', color: 'info', icon: 'i-lucide-info' })
    }
}

// ── Salva / elimina ──
const form = useTemplateRef<Form<Schema>>('form')
const schema = z.object({
    description: z.string().trim().min(1, 'Scrivi cosa fai').max(255),
    quantity: z.string().trim().min(1, 'Scrivi la quantità').refine(value => (parseNumber(value) ?? 0) > 0, 'Scrivi un numero più grande di zero, es. 181'),
    unit_price: z.string().trim().min(1, 'Scrivi il prezzo').refine(value => (parseNumber(value) ?? -1) >= 0, 'Scrivi un numero, es. 4,50'),
    coats: z.number().optional(),
})
type Schema = z.output<typeof schema>

async function save(): Promise<void> {
    saving.value = true

    try {
        if (props.line?.id) {
            await quotes.updateLine(props.quoteId, props.line.id, payload())
            emit('saved', 'Lavoro aggiornato')
        }
        else {
            await quotes.addLine(props.quoteId, payload())
            emit('saved', 'Lavoro aggiunto')
        }

        open.value = false
    }
    catch (error) {
        const errors = validationErrors(error)

        if (errors.length > 0) {
            form.value?.setErrors(errors)
        }
        else {
            toast.add({ title: errorMessage(error), color: 'error', icon: 'i-lucide-circle-alert' })
        }
    }
    finally {
        saving.value = false
    }
}

async function remove(): Promise<void> {
    if (!props.line?.id) {
        return
    }

    saving.value = true

    try {
        await quotes.removeLine(props.quoteId, props.line.id)
        emit('saved', `"${props.line.description}" eliminata`)
        open.value = false
    }
    catch (error) {
        toast.add({ title: errorMessage(error), color: 'error', icon: 'i-lucide-circle-alert' })
    }
    finally {
        saving.value = false
    }
}

const title = computed(() => {
    if (view.value === 'materials') {
        return 'Altro materiale'
    }

    if (view.value === 'perUnit') {
        return perUnitMaterial.value?.name ?? 'Consumo'
    }

    return editing.value ? 'Modifica lavoro' : isFree.value ? 'Riga libera' : state.description || 'Nuovo lavoro'
})
</script>

<template>
    <AppSheet
        v-model:open="open"
        :title="title"
    >
        <!-- Riga -->
        <UForm
            v-if="view === 'form'"
            ref="form"
            :schema="schema"
            :state="state"
            :validate-on="['input']"
            novalidate
            class="flex flex-col gap-5"
            @submit="save"
        >
            <UFormField
                label="Descrizione"
                name="description"
                required
                size="xl"
                :help="isFree ? 'Come la leggerà il cliente sul PDF' : undefined"
            >
                <UInput
                    v-model="state.description"
                    :placeholder="isFree ? 'Es. Trattamento portone in legno' : undefined"
                    :autofocus="isFree"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                label="Quantità"
                name="quantity"
                required
                size="xl"
            >
                <UInput
                    v-model="state.quantity"
                    inputmode="decimal"
                    :autofocus="!isFree"
                    placeholder="0"
                    class="w-full"
                    :ui="{ base: 'h-16 text-3xl font-extrabold tabular-nums' }"
                >
                    <template #trailing>
                        <span class="text-xl font-bold text-cemento-700">{{ JOB_UNITS[unit].short }}</span>
                    </template>
                </UInput>
            </UFormField>

            <UFormField
                v-if="showCoats"
                label="Mani"
                name="coats"
                size="xl"
            >
                <AppSegmented
                    v-model="state.coats"
                    :items="coatItems"
                    label="Mani"
                />
            </UFormField>

            <div class="grid grid-cols-2 items-end gap-3">
                <UFormField
                    label="Prezzo"
                    name="unit_price"
                    required
                    size="xl"
                    :hint="`€/${JOB_UNITS[unit].per}`"
                >
                    <UInput
                        v-model="state.unit_price"
                        inputmode="decimal"
                        placeholder="0,00"
                        class="w-full"
                    >
                        <template #trailing>
                            <span class="text-base font-bold text-cemento-700">€</span>
                        </template>
                    </UInput>
                </UFormField>
                <div
                    class="flex flex-col items-end rounded-xl bg-cemento-100 px-4 py-2"
                    aria-live="polite"
                >
                    <span class="text-sm font-semibold text-cemento-700">Importo</span>
                    <span class="text-2xl font-extrabold text-cemento-900 tabular-nums">{{ amount === null ? '–' : formatEuro(amount) }}</span>
                </div>
            </div>

            <!-- Materiali: solo per le righe da lavorazione -->
            <section
                v-if="!isFree"
                class="flex flex-col gap-2"
                aria-label="Materiali"
            >
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-bold text-cemento-900">
                        Materiali
                    </h3>
                    <span
                        v-if="calculating"
                        class="flex items-center gap-1 text-sm text-cemento-600"
                    >
                        <UIcon
                            name="i-lucide-loader-circle"
                            class="size-4 animate-spin"
                        />
                        Calcolo…
                    </span>
                    <span
                        v-else-if="valid && materialsCost > 0"
                        class="flex items-center gap-1 text-sm text-cemento-600"
                    >
                        <UIcon
                            name="i-lucide-lock"
                            class="size-3.5"
                        />
                        costo {{ formatEuro(materialsCost) }}
                    </span>
                </div>

                <p
                    v-if="materials.length === 0"
                    class="text-base text-cemento-700"
                >
                    Nessun materiale suggerito per questo lavoro.
                </p>

                <ul
                    v-else
                    class="flex flex-col"
                >
                    <li
                        v-for="material in materials"
                        :key="material.material_id"
                        class="border-b border-cemento-200 py-3 last:border-b-0"
                    >
                        <UCheckbox
                            :model-value="material.selected"
                            :label="material.material?.name"
                            :description="requirement(material) ?? (material.selected ? (valid ? undefined : 'Scrivi la quantità per il calcolo') : 'Non usato')"
                            size="xl"
                            :ui="{ label: 'text-base font-bold text-cemento-900', description: 'text-sm font-semibold text-cemento-700 tabular-nums' }"
                            @update:model-value="toggle(material, $event === true)"
                        />
                    </li>
                </ul>

                <UButton
                    label="Altro materiale"
                    icon="i-lucide-plus"
                    color="neutral"
                    variant="outline"
                    class="self-start"
                    @click="openMaterials"
                />
            </section>

            <div class="mt-1 flex flex-col gap-2">
                <UButton
                    type="submit"
                    :label="editing ? 'Salva' : 'Aggiungi al preventivo'"
                    :icon="editing ? 'i-lucide-check' : 'i-lucide-plus'"
                    size="xl"
                    block
                    :loading="saving"
                    class="min-h-14 text-lg"
                />
                <UButton
                    v-if="editing"
                    label="Elimina questo lavoro"
                    icon="i-lucide-trash-2"
                    color="error"
                    variant="ghost"
                    block
                    :disabled="saving"
                    @click="remove"
                />
            </div>
        </UForm>

        <!-- Altro materiale -->
        <div
            v-else-if="view === 'materials'"
            class="flex flex-col gap-3"
        >
            <UInput
                v-model="materialSearch"
                type="search"
                icon="i-lucide-search"
                placeholder="Cerca materiale"
                aria-label="Cerca materiale"
                autofocus
                class="w-full"
            />

            <div
                v-if="loadingMaterials"
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
                    v-for="material in availableMaterials"
                    :key="material.id"
                >
                    <button
                        type="button"
                        class="flex min-h-16 w-full items-center justify-between gap-3 border-b border-cemento-200 py-3 text-left"
                        @click="pickMaterial(material)"
                    >
                        <span class="flex min-w-0 flex-col">
                            <span class="text-lg font-bold text-cemento-900">{{ material.name }}</span>
                            <span class="text-base text-cemento-700">{{ material.package_label }}<template v-if="material.cost_price !== null"> · {{ formatEuro(material.cost_price) }}</template></span>
                        </span>
                        <UIcon
                            name="i-lucide-plus"
                            class="size-5 shrink-0 text-cemento-500"
                        />
                    </button>
                </li>
            </ul>

            <UButton
                label="Torna al lavoro"
                icon="i-lucide-arrow-left"
                color="neutral"
                variant="ghost"
                block
                @click="view = 'form'"
            />
        </div>

        <!-- Consumo per unità di un materiale senza resa -->
        <form
            v-else
            class="flex flex-col gap-5"
            novalidate
            @submit.prevent="confirmPerUnit"
        >
            <p class="text-base text-cemento-700">
                Questo materiale non ha una resa: quanto ne usi per ogni {{ JOB_UNITS[unit].per }}?
            </p>
            <UFormField
                label="Consumo"
                size="xl"
                :hint="`${perUnitMaterial?.base_unit} per ${JOB_UNITS[unit].per}`"
            >
                <UInput
                    v-model="perUnit"
                    inputmode="decimal"
                    autofocus
                    placeholder="Es. 0,4"
                    class="w-full"
                >
                    <template #trailing>
                        <span class="text-base font-bold text-cemento-700">{{ perUnitMaterial?.base_unit }}</span>
                    </template>
                </UInput>
            </UFormField>
            <div class="flex flex-col gap-2">
                <UButton
                    type="submit"
                    label="Aggiungi materiale"
                    icon="i-lucide-plus"
                    size="xl"
                    block
                    :disabled="(parseNumber(perUnit) ?? 0) <= 0"
                    class="min-h-14 text-lg"
                />
                <UButton
                    label="Scegli un altro materiale"
                    color="neutral"
                    variant="ghost"
                    block
                    @click="view = 'materials'"
                />
            </div>
        </form>
    </AppSheet>
</template>
