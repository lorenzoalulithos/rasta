<script setup lang="ts">
// Finché la prima navigazione non è finita (il middleware sta caricando l'utente) resta la schermata di avvio
const ready = ref(false)

useRouter().isReady().finally(() => {
    ready.value = true
})
</script>

<template>
    <UApp :toaster="{ position: 'top-center' }">
        <NuxtPwaManifest />
        <NuxtPwaAssets />
        <AppSplash v-if="!ready" />
        <NuxtLayout>
            <NuxtPage />
        </NuxtLayout>
    </UApp>
</template>
