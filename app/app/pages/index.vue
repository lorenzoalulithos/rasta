<script setup lang="ts">
// M0.2: verifica che la PWA raggiunga le API (CORS compreso)
interface Ping {
    data: {
        status: string
        app: string
        time: string
    }
}

const config = useRuntimeConfig()

const { data, error, status, refresh } = await useFetch<Ping>('/ping', {
    baseURL: config.public.apiBase,
    server: false,
})
</script>

<template>
    <UContainer class="py-10 max-w-lg">
        <h1 class="text-2xl font-bold mb-6">
            App Artigiani
        </h1>

        <UCard>
            <template #header>
                <div class="flex items-center justify-between gap-3">
                    <span class="font-semibold">Collegamento API</span>
                    <UBadge
                        v-if="status === 'pending'"
                        color="neutral"
                        variant="subtle"
                        label="Controllo…"
                    />
                    <UBadge
                        v-else-if="error"
                        color="error"
                        variant="subtle"
                        label="Non raggiungibile"
                    />
                    <UBadge
                        v-else
                        color="success"
                        variant="subtle"
                        label="Ok"
                    />
                </div>
            </template>

            <p
                v-if="error"
                class="text-sm"
            >
                Non riesco a contattare <code>{{ config.public.apiBase }}</code>. DDEV è acceso?
            </p>
            <pre
                v-else-if="data"
                class="text-sm whitespace-pre-wrap"
            >{{ data }}</pre>

            <template #footer>
                <UButton
                    icon="i-lucide-refresh-cw"
                    label="Riprova"
                    size="lg"
                    block
                    :loading="status === 'pending'"
                    @click="refresh()"
                />
            </template>
        </UCard>
    </UContainer>
</template>
