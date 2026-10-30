<script setup lang="ts">
import * as z from 'zod'
import type { Form, FormSubmitEvent } from '@nuxt/ui'
import type { Customer, Site } from '~/types/api'

// Scelta del cantiere del cliente con "+ Nuovo cantiere" inline (docs/flows.md §2.2)
const props = defineProps<{
    customer: Customer
    sites: Site[]
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ select: [site: Site] }>()

const customers = useCustomers()
const toast = useToast()

const creating = ref(false)

watch(open, (value) => {
    if (value) {
        creating.value = props.sites.length === 0
        reset()
    }
})

function choose(site: Site): void {
    emit('select', site)
    open.value = false
}

const optional = z.string().trim().max(255).optional()
const schema = z.object({
    name: z.string().trim().min(1, 'Scrivi un nome per il cantiere').max(255),
    sameAddress: z.boolean(),
    address: optional,
    zip: optional,
    city: optional,
    province: z.string().trim().max(2, 'Due lettere, es. MI').optional(),
})

type Schema = z.output<typeof schema>

const form = useTemplateRef<Form<Schema>>('form')
const state = reactive<Partial<Schema>>({})
const saving = ref(false)

function reset(): void {
    Object.assign(state, { name: '', sameAddress: true, address: '', zip: '', city: '', province: '' })
}

const customerAddress = computed(() => [props.customer.address, [props.customer.zip, props.customer.city].filter(Boolean).join(' ')].filter(Boolean).join(', '))

async function save(event: FormSubmitEvent<Schema>): Promise<void> {
    saving.value = true
    const { sameAddress, ...fields } = event.data

    // Indirizzo vuoto = stesso del cliente (domain.md §3.4)
    const body = sameAddress
        ? { name: fields.name }
        : Object.fromEntries(Object.entries(fields).filter(([, value]) => value !== '' && value !== undefined)) as typeof fields

    try {
        const response = await customers.createSite(props.customer.id, body)
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
</script>

<template>
    <AppSheet
        v-model:open="open"
        :title="creating ? 'Nuovo cantiere' : 'Scegli il cantiere'"
        :description="customer.name"
    >
        <div
            v-if="!creating"
            class="flex flex-col gap-3"
        >
            <UButton
                label="Nuovo cantiere"
                icon="i-lucide-map-pin-plus"
                block
                class="min-h-14 justify-start text-base"
                @click="creating = true"
            />

            <ul class="flex flex-col">
                <li
                    v-for="site in sites"
                    :key="site.id"
                >
                    <button
                        type="button"
                        class="flex min-h-16 w-full items-center justify-between gap-3 border-b border-cemento-200 py-3 text-left"
                        @click="choose(site)"
                    >
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate text-lg font-bold text-cemento-900">{{ site.name }}</span>
                            <span class="truncate text-base text-cemento-700">{{ site.address ? [site.address, site.city].filter(Boolean).join(', ') : 'Indirizzo del cliente' }}</span>
                        </span>
                        <UIcon
                            name="i-lucide-chevron-right"
                            class="size-5 shrink-0 text-cemento-500"
                        />
                    </button>
                </li>
            </ul>
        </div>

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
                label="Nome del cantiere"
                name="name"
                required
                size="xl"
                help="Come lo chiami tu: «Vano scala», «Casa al mare»…"
            >
                <UInput
                    v-model="state.name"
                    autofocus
                    autocomplete="off"
                    placeholder="Es. Vano scala"
                    class="w-full"
                />
            </UFormField>

            <USwitch
                v-model="state.sameAddress"
                label="Stesso indirizzo del cliente"
                :description="customerAddress || undefined"
                size="xl"
            />

            <template v-if="!state.sameAddress">
                <UFormField
                    label="Indirizzo del cantiere"
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
            </template>

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
                    v-if="sites.length > 0"
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
