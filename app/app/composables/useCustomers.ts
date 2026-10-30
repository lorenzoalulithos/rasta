import type { Customer, CustomerInput, Item, PageQuery, Paginated, Site, SiteInput } from '~/types/api'

/**
 * Clienti e cantieri (docs/api.md "Clienti e cantieri"). Solo chiamate.
 */
export function useCustomers() {
    const api = useApi()

    return {
        list: (query: PageQuery & { search?: string } = {}) => api<Paginated<Customer>>('/customers', { query }),
        get: (id: string) => api<Item<Customer>>(`/customers/${id}`),
        create: (body: CustomerInput) => api<Item<Customer>>('/customers', { method: 'POST', body }),
        update: (id: string, body: Partial<CustomerInput>) => api<Item<Customer>>(`/customers/${id}`, { method: 'PATCH', body }),
        remove: (id: string) => api(`/customers/${id}`, { method: 'DELETE' }),

        sites: (customerId: string) => api<Item<Site[]>>(`/customers/${customerId}/sites`),
        createSite: (customerId: string, body: SiteInput) => api<Item<Site>>(`/customers/${customerId}/sites`, { method: 'POST', body }),
        updateSite: (id: string, body: Partial<SiteInput>) => api<Item<Site>>(`/sites/${id}`, { method: 'PATCH', body }),
        removeSite: (id: string) => api(`/sites/${id}`, { method: 'DELETE' }),
    }
}
