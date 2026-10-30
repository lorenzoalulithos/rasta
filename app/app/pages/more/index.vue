<script setup lang="ts">
useHead({ title: 'Altro · duemani' })

const auth = useAuthStore()

async function logout(): Promise<void> {
    await auth.logout()
    await navigateTo('/login')
}
</script>

<template>
    <section class="flex flex-col gap-6">
        <h1 class="font-display text-4xl font-extrabold tracking-tight text-cemento-900">
            Altro
        </h1>

        <div class="flex flex-col gap-4 rounded-3xl bg-white p-6">
            <div
                v-if="auth.user"
                class="flex flex-col gap-0.5"
            >
                <span class="text-lg font-bold text-cemento-900">{{ auth.user.name }}</span>
                <span class="text-base text-cemento-700">{{ auth.user.email }}</span>
                <span
                    v-if="auth.user.tenant"
                    class="text-base text-cemento-700"
                >{{ auth.user.tenant.name }}</span>
            </div>
            <p class="text-base text-cemento-700">
                Clienti, catalogo e impostazioni arrivano presto.
            </p>
            <UButton
                label="Esci"
                icon="i-lucide-log-out"
                color="neutral"
                variant="outline"
                class="self-start"
                @click="logout"
            />
        </div>
    </section>
</template>
