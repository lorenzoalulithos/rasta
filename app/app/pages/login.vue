<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: false })
useHead({ title: 'Entra · duemani' })

const schema = z.object({
    email: z.email('Scrivi un indirizzo email valido'),
    password: z.string().min(1, 'Scrivi la password'),
})

type Schema = z.output<typeof schema>

const state = reactive<Partial<Schema>>({ email: '', password: '' })
const showPassword = ref(false)
const loading = ref(false)
const failure = ref<string | null>(null)

const auth = useAuthStore()
const route = useRoute()

async function onSubmit(event: FormSubmitEvent<Schema>): Promise<void> {
    loading.value = true
    failure.value = null

    try {
        await auth.login(event.data.email, event.data.password)
        const target = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
        // Solo percorsi interni, mai di nuovo il login
        const redirect = target.startsWith('/') && !target.startsWith('//') && normalizePath(target) !== '/login' ? target : '/'
        await navigateTo(redirect)
    }
    catch (error: unknown) {
        failure.value = loginError(error)
    }
    finally {
        loading.value = false
    }
}

function loginError(error: unknown): string {
    const response = (error as { response?: { status: number, _data?: { errors?: Record<string, string[]>, message?: string } } }).response

    if (response === undefined) {
        return 'Non riesco a collegarmi. Controlla di essere connesso a internet e riprova.'
    }

    if (response.status === 429) {
        return 'Troppi tentativi. Aspetta un minuto e riprova.'
    }

    return response._data?.errors?.email?.[0] ?? response._data?.message ?? 'Qualcosa non ha funzionato. Riprova.'
}
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-cemento-100">
        <div class="flex flex-col items-center gap-3 bg-cemento-900 px-6 pt-[max(3.5rem,env(safe-area-inset-top))] pb-14 text-center">
            <AppLogo
                tone="dark"
                :show-name="false"
                class="[&_svg]:size-16"
            />
            <h1 class="font-display text-5xl font-extrabold tracking-tight text-white">
                duemani
            </h1>
            <p class="max-w-xs text-base text-cemento-300">
                Preventivi e magazzino per chi lavora con le mani
            </p>
        </div>

        <div class="-mt-8 flex flex-1 justify-center px-4 pb-10">
            <div class="flex w-full max-w-md flex-col gap-6 self-start rounded-3xl bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-1">
                    <h2 class="font-display text-3xl font-extrabold tracking-tight text-cemento-900">
                        Entra
                    </h2>
                    <p class="text-base text-cemento-700">
                        Con la tua email e la password. Resti dentro finché non esci tu.
                    </p>
                </div>

                <UAlert
                    v-if="failure"
                    color="error"
                    variant="subtle"
                    icon="i-lucide-circle-alert"
                    :title="failure"
                />

                <UForm
                    :schema="schema"
                    :state="state"
                    class="flex flex-col gap-5"
                    @submit="onSubmit"
                >
                    <UFormField
                        label="Email"
                        name="email"
                        size="xl"
                    >
                        <UInput
                            v-model="state.email"
                            type="email"
                            inputmode="email"
                            autocomplete="username"
                            autocapitalize="off"
                            placeholder="nome@esempio.it"
                            icon="i-lucide-mail"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        label="Password"
                        name="password"
                        size="xl"
                    >
                        <UInput
                            v-model="state.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            icon="i-lucide-lock"
                            class="w-full"
                            :ui="{ trailing: 'pe-1' }"
                        >
                            <template #trailing>
                                <UButton
                                    color="neutral"
                                    variant="link"
                                    size="md"
                                    :icon="showPassword ? 'i-lucide-eye-off' : 'i-lucide-eye'"
                                    :aria-label="showPassword ? 'Nascondi password' : 'Mostra password'"
                                    :aria-pressed="showPassword"
                                    @click="showPassword = !showPassword"
                                />
                            </template>
                        </UInput>
                    </UFormField>

                    <UButton
                        type="submit"
                        label="Entra"
                        size="xl"
                        block
                        trailing-icon="i-lucide-arrow-right"
                        :loading="loading"
                        class="mt-1 min-h-14 justify-center text-lg"
                    />
                </UForm>
            </div>
        </div>
    </div>
</template>
