/**
 * Tutte le chiamate alle API passano da qui: base URL, JSON e Bearer (docs/stack.md "State").
 * Un 401 vuol dire token non più valido: si torna al login.
 */
export function useApi(): ReturnType<typeof $fetch.create> {
    const config = useRuntimeConfig()
    const auth = useAuthStore()

    return $fetch.create({
        baseURL: config.public.apiBase,
        headers: { Accept: 'application/json' },
        onRequest({ options }) {
            if (auth.token !== null) {
                options.headers.set('Authorization', `Bearer ${auth.token}`)
            }
        },
        async onResponseError({ response, request }) {
            if (response.status === 401 && !String(request).endsWith('/auth/login')) {
                auth.forget()
                await navigateTo('/login')
            }
        },
    })
}
