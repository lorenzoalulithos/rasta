import { defineStore } from 'pinia'
import type { Item, LoginResponse, User } from '~/types/api'

const TOKEN_KEY = 'duemani.token'

// `device_name` del token: l'API accetta al massimo 100 caratteri
const DEVICE_NAME_LENGTH = 100

/**
 * Token Sanctum salvato nel dispositivo: il socio resta dentro finché non esce (docs/api.md "Auth").
 */
export const useAuthStore = defineStore('auth', () => {
    const token = ref<string | null>(readToken())
    const user = ref<User | null>(null)

    const loggedIn = computed(() => token.value !== null)

    function setToken(value: string | null): void {
        token.value = value

        try {
            if (value === null) {
                localStorage.removeItem(TOKEN_KEY)
            }
            else {
                localStorage.setItem(TOKEN_KEY, value)
            }
        }
        catch {
            // Storage bloccato (navigazione privata): il token resta solo in memoria
        }
    }

    async function login(email: string, password: string): Promise<void> {
        const response = await useApi()<LoginResponse>('/auth/login', {
            method: 'POST',
            body: { email, password, device_name: navigator.userAgent.slice(0, DEVICE_NAME_LENGTH) },
        })

        setToken(response.data.token)
        user.value = response.data.user
    }

    /** Ricarica l'utente all'avvio; un token revocato fa tornare al login. */
    async function fetchUser(): Promise<void> {
        if (token.value === null || user.value !== null) {
            return
        }

        const response = await useApi()<Item<User>>('/auth/me')
        user.value = response.data
    }

    async function logout(): Promise<void> {
        try {
            await useApi()('/auth/logout', { method: 'POST' })
        }
        finally {
            forget()
        }
    }

    /** Dimentica la sessione senza chiamare l'API (token già non valido). */
    function forget(): void {
        setToken(null)
        user.value = null
    }

    return { token, user, loggedIn, login, fetchUser, logout, forget }
})

function readToken(): string | null {
    try {
        return localStorage.getItem(TOKEN_KEY)
    }
    catch {
        return null
    }
}
