// Senza token si va al login; da loggati il login riporta alla home
export default defineNuxtRouteMiddleware(async (to) => {
    const auth = useAuthStore()
    const path = normalizePath(to.path)
    const isLogin = path === '/login'

    if (!auth.loggedIn) {
        return isLogin ? undefined : navigateTo({ path: '/login', query: path === '/' ? {} : { redirect: to.fullPath } })
    }

    if (isLogin) {
        return navigateTo('/')
    }

    try {
        await auth.fetchUser()
    }
    catch {
        // Un 401 ha già dimenticato il token (useApi): si torna al login.
        // Senza rete invece si resta dentro (sola consultazione, docs/flows.md "PWA")
        if (!auth.loggedIn) {
            return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
        }
    }
})
