// Tipi delle risorse API, scritti a mano da docs/api.md (docs/stack.md "Tipi").
// F4.1: solo quanto serve all'autenticazione; il resto arriva con F4.2.

export interface Tenant {
    id: string
    name: string
    city: string | null
}

export interface User {
    id: string
    name: string
    email: string
    tenant?: Tenant
}

export interface LoginResponse {
    data: {
        token: string
        user: User
    }
}

export interface Item<T> {
    data: T
}
