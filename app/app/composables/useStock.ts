import type { Item, PageQuery, Paginated, Purchase, PurchaseInput, StockMovement, StockQuery, StockRow } from '~/types/api'

/**
 * Magazzino, movimenti e acquisti (docs/api.md "Magazzino"). Solo chiamate.
 */
export function useStock() {
    const api = useApi()

    return {
        list: (query: StockQuery = {}) => api<Paginated<StockRow>>('/stock', { query }),
        /** Rettifica: valori assoluti di confezioni chiuse e aperte. */
        adjust: (materialId: string, body: { closed: number, opened: number, notes?: string | null }) => api<Item<StockRow>>(`/stock/${materialId}/adjust`, { method: 'POST', body }),
        setMinStock: (materialId: string, minStock: number) => api<Item<StockRow>>(`/stock/${materialId}`, { method: 'PATCH', body: { min_stock: minStock } }),
        movements: (query: PageQuery & { material_id?: string } = {}) => api<Paginated<StockMovement>>('/stock/movements', { query }),

        purchases: (query: PageQuery & { paid?: 0 | 1 } = {}) => api<Paginated<Purchase>>('/purchases', { query }),
        purchase: (id: string) => api<Item<Purchase>>(`/purchases/${id}`),
        createPurchase: (body: PurchaseInput) => api<Item<Purchase>>('/purchases', { method: 'POST', body }),
        updatePurchase: (id: string, body: { paid?: boolean, notes?: string | null }) => api<Item<Purchase>>(`/purchases/${id}`, { method: 'PATCH', body }),
    }
}
