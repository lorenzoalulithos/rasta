<template>
    <UApp :toaster="{ position: 'top-center' }">
        <NuxtPwaManifest />
        <NuxtPwaAssets />
        <NuxtLayout>
            <NuxtPage />
        </NuxtLayout>
    </UApp>
</template>
