<script setup lang="ts">
import * as z from 'zod'
import type { Form, FormSubmitEvent } from '@nuxt/ui'
import type { Customer } from '~/types/api'

// Scelta del cliente con "+ Nuovo cliente" inline (docs/flows.md §2.2)
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ select: [customer: Customer] }>()

const customers = useCustomers()
const toast = useToast()

const search = ref('')
const items = ref<Customer[]>([])
const loading = ref(false)
const creating = ref(false)

let requestId = 0
let debounce: ReturnType<typeof setTimeout> | undefined

async function load(): Promise<void> {
    const id = ++requestId
    loading.value = true

    try {
        const response = await customers.list({ search: search.value.trim() || undefined, per_page: 50 })

        if (id === requestId) {
            items.value = response.data
        }
    }
    catch (error) {
        toast.add({ title: errorMessage(error), color: 'error', icon: 'i-lucide-circle-alert' })
    }
    finally {
        if (id === requestId) {
            loading.value = false
        }
    }
}

watch(search, () => {
    clearTimeout(debounce)
    debounce = setTimeout(load, 300)
})

watch(open, (value) => {
    if (value) {
        creating.value = false
        load()
    }
})

function choose(customer: Customer): void {
    emit('select', customer)
    open.value = false
}

// ── Nuovo cliente: solo il nome è obbligatorio ──
const optional = z.string().trim().max(255).optional()
const schema = z.object({
    name: z.string().trim().min(1, 'Scrivi il nome del cliente').max(255),
    phone: optional,
    email: z.union([z.literal(''), z.email('Email non valida')]).optional(),
    address: optional,
    zip: optional,
    city: optional,
    province: z.string().trim().max(2, 'Due lettere, es. MI').optional(),
    tax_code: optional,
    vat_number: optional,
})

type Schema = z.output<typeof schema>

const form = useTemplateRef<Form<Schema>>('form')
const state = reactive<Partial<Schema>>({})
const saving = ref(false)

function startCreating(): void {
    Object.assign(state, { name: search.value.trim(), phone: '', email: '', address: '', zip: '', city: '', province: '', tax_code: '', vat_number: '' })
    creating.value = true
}

async function save(event: FormSubmitEvent<Schema>): Promise<void> {
    saving.value = true

    // I campi lasciati vuoti non si mandano
    const body = Object.fromEntries(Object.entries(event.data).filter(([, value]) => value !== '' && value !== undefined)) as Schema

    try {
        const response = await customers.create(body)
        choose(response.data)
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

onBeforeUnmount(() => clearTimeout(debounce))
</script>

<template>
    <AppSheet
        v-model:open="open"
        :title="creating ? 'Nuovo cliente' : 'Scegli il cliente'"
    >
        <!-- Lista -->
        <div
            v-if="!creating"
            class="flex flex-col gap-3"
        >
            <UInput
                v-model="search"
                type="search"
                icon="i-lucide-search"
                placeholder="Cerca per nome, città o telefono"
                aria-label="Cerca cliente"
                class="w-full"
            />

            <UButton
                label="Nuovo cliente"
                icon="i-lucide-user-plus"
                block
                class="min-h-14 justify-start text-base"
                @click="startCreating"
            />

            <ul
                class="flex flex-col"
                :aria-busy="loading"
            >
                <li
                    v-for="customer in items"
                    :key="customer.id"
                >
                    <button
                        type="button"
                        class="flex min-h-16 w-full items-center justify-between gap-3 border-b border-cemento-200 py-3 text-left"
                        @click="choose(customer)"
                    >
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate text-lg font-bold text-cemento-900">{{ customer.name }}</span>
                            <span
                                v-if="customer.city || customer.phone"
                                class="truncate text-base text-cemento-700"
                            >{{ [customer.city, customer.phone].filter(Boolean).join(' · ') }}</span>
                        </span>
                        <UIcon
                            name="i-lucide-chevron-right"
                            class="size-5 shrink-0 text-cemento-500"
                        />
                    </button>
                </li>
            </ul>

            <p
                v-if="!loading && items.length === 0"
                class="py-4 text-center text-base text-cemento-700"
            >
                {{ search.trim() ? `Nessun cliente "${search.trim()}". Crealo qui sopra.` : 'Ancora nessun cliente. Crealo qui sopra.' }}
            </p>
        </div>

        <!-- Nuovo cliente -->
        <UForm
            v-else
            ref="form"
            :schema="schema"
            :state="state"
            :validate-on="['input']"
            novalidate
            class="flex flex-col gap-4"
            @submit="save"
        >
            <UFormField
                label="Nome o ragione sociale"
                name="name"
                required
                size="xl"
            >
                <UInput
                    v-model="state.name"
                    autofocus
                    autocomplete="off"
                    placeholder="Es. Condominio Belissi"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                label="Telefono"
                name="phone"
                size="xl"
            >
                <UInput
                    v-model="state.phone"
                    type="tel"
                    inputmode="tel"
                    class="w-full"
                />
            </UFormField>

            <UCollapsible :ui="{ content: 'flex flex-col gap-4 pt-4' }">
                <UButton
                    label="Altri dati (indirizzo, CF, P.IVA)"
                    color="neutral"
                    variant="ghost"
                    trailing-icon="i-lucide-chevron-down"
                    class="group -mx-2.5 self-start text-base"
                    :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform' }"
                />

                <template #content>
                    <UFormField
                        label="Email"
                        name="email"
                        size="xl"
                    >
                        <UInput
                            v-model="state.email"
                            type="email"
                            inputmode="email"
                            autocapitalize="off"
                            class="w-full"
                        />
                    </UFormField>
                    <UFormField
                        label="Indirizzo"
                        name="address"
                        size="xl"
                    >
                        <UInput
                            v-model="state.address"
                            class="w-full"
                        />
                    </UFormField>
                    <div class="grid grid-cols-[1fr_2fr_5rem] gap-3">
                        <UFormField
                            label="CAP"
                            name="zip"
                            size="xl"
                        >
                            <UInput
                                v-model="state.zip"
                                inputmode="numeric"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField
                            label="Città"
                            name="city"
                            size="xl"
                        >
                            <UInput
                                v-model="state.city"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField
                            label="Prov."
                            name="province"
                            size="xl"
                        >
                            <UInput
                                v-model="state.province"
                                maxlength="2"
                                class="w-full uppercase"
                            />
                        </UFormField>
                    </div>
                    <UFormField
                        label="Codice fiscale"
                        name="tax_code"
                        size="xl"
                    >
                        <UInput
                            v-model="state.tax_code"
                            autocapitalize="characters"
                            class="w-full"
                        />
                    </UFormField>
                    <UFormField
                        label="Partita IVA"
                        name="vat_number"
                        size="xl"
                    >
                        <UInput
                            v-model="state.vat_number"
                            inputmode="numeric"
                            class="w-full"
                        />
                    </UFormField>
                </template>
            </UCollapsible>

            <div class="mt-2 flex flex-col gap-2">
                <UButton
                    type="submit"
                    label="Salva e scegli"
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
                    @click="creating = false"
                />
            </div>
        </UForm>
    </AppSheet>
</template>
