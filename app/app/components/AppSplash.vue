<script setup lang="ts">
// Schermata di avvio, uguale a spa-loading-template.html: copre il caricamento dell'utente all'apertura
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-5 bg-cemento-900"
        role="status"
        aria-label="Caricamento di duemani"
    >
        <AppLogo
            tone="dark"
            :show-name="false"
            class="[&_svg]:size-22"
        />
        <span class="font-display text-4xl font-extrabold tracking-tight text-white">duemani</span>
        <span class="h-1 w-30 overflow-hidden rounded bg-cemento-800">
            <span class="block h-full w-2/5 rounded bg-primary motion-safe:animate-[duemani-splash_1.1s_ease-in-out_infinite] motion-reduce:w-full" />
        </span>
    </div>
</template>

<style>
@keyframes duemani-splash {
    0% { transform: translateX(-100%) }
    100% { transform: translateX(250%) }
}
</style>
