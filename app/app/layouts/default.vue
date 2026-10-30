<script setup lang="ts">
// Shell (docs/flows.md §0): bottom bar su mobile, sidebar da lg in su
const route = useRoute()
const auth = useAuthStore()

const tabs = [
    { label: 'Home', to: '/', icon: 'i-lucide-house' },
    { label: 'Preventivi', to: '/quotes', icon: 'i-lucide-file-text' },
    { label: 'Magazzino', to: '/stock', icon: 'i-lucide-package' },
    { label: 'Altro', to: '/more', icon: 'i-lucide-ellipsis' },
]

function isActive(to: string): boolean {
    const path = normalizePath(route.path)

    return to === '/' ? path === '/' : path === to || path.startsWith(`${to}/`)
}

async function logout(): Promise<void> {
    await auth.logout()
    await navigateTo('/login')
}
</script>

<template>
    <div class="min-h-dvh lg:flex">
        <!-- Sidebar desktop -->
        <aside class="hidden lg:flex lg:w-64 lg:shrink-0 lg:flex-col lg:gap-8 lg:sticky lg:top-0 lg:h-dvh bg-cemento-900 px-4 py-6">
            <NuxtLink
                to="/"
                class="px-3"
                aria-label="duemani, vai alla home"
            >
                <AppLogo tone="dark" />
            </NuxtLink>

            <nav
                class="flex flex-col gap-1"
                aria-label="Sezioni"
            >
                <NuxtLink
                    v-for="tab in tabs"
                    :key="tab.to"
                    :to="tab.to"
                    class="flex items-center gap-3 rounded-xl px-3 py-3 text-base font-semibold transition-colors"
                    :class="isActive(tab.to) ? 'bg-primary text-cemento-900' : 'text-cemento-200 hover:bg-white/10 hover:text-white'"
                    :aria-current="isActive(tab.to) ? 'page' : undefined"
                >
                    <UIcon
                        :name="tab.icon"
                        class="size-5"
                    />
                    {{ tab.label }}
                </NuxtLink>
            </nav>

            <div class="mt-auto flex flex-col gap-2 px-3">
                <span
                    v-if="auth.user"
                    class="text-sm text-cemento-300 truncate"
                >{{ auth.user.name }}</span>
                <UButton
                    label="Esci"
                    icon="i-lucide-log-out"
                    color="neutral"
                    variant="ghost"
                    size="md"
                    class="-mx-2.5 self-start text-cemento-200 hover:bg-white/10 hover:text-white"
                    @click="logout"
                />
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Testata mobile -->
            <header class="flex items-center justify-between px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-2 lg:hidden">
                <NuxtLink
                    to="/"
                    aria-label="duemani, vai alla home"
                >
                    <AppLogo />
                </NuxtLink>
            </header>

            <main class="mx-auto w-full max-w-5xl flex-1 px-4 pt-2 pb-28 lg:px-10 lg:py-10">
                <slot />
            </main>
        </div>

        <!-- Bottom bar mobile -->
        <nav
            class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 bg-cemento-900 pb-[env(safe-area-inset-bottom)] lg:hidden"
            aria-label="Sezioni"
        >
            <NuxtLink
                v-for="tab in tabs"
                :key="tab.to"
                :to="tab.to"
                class="flex min-h-16 flex-col items-center justify-center gap-1 text-xs font-bold"
                :class="isActive(tab.to) ? 'text-primary' : 'text-cemento-300'"
                :aria-current="isActive(tab.to) ? 'page' : undefined"
            >
                <span
                    class="flex h-8 w-14 items-center justify-center rounded-full transition-colors"
                    :class="isActive(tab.to) ? 'bg-primary text-cemento-900' : ''"
                >
                    <UIcon
                        :name="tab.icon"
                        class="size-6"
                    />
                </span>
                {{ tab.label }}
            </NuxtLink>
        </nav>
    </div>
</template>
