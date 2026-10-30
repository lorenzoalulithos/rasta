import type {
    CompleteResponse, CompletionItem, Consumption, Item, Paginated, PreviewLineInput, PreviewResponse, Quote,
    QuoteLineInput, QuoteLineResponse, QuoteQuery, QuoteUpdate, TransitionResponse, TransitionTarget,
} from '~/types/api'

/**
 * Preventivi, righe, calcolo, stati, PDF e chiusura cantiere (docs/api.md "Preventivi"). Solo chiamate.
 */
export function useQuotes() {
    const api = useApi()

    return {
        list: (query: QuoteQuery = {}) => api<Paginated<Quote>>('/quotes', { query }),
        get: (id: string) => api<Item<Quote>>(`/quotes/${id}`),
        create: (siteId: string) => api<Item<Quote>>('/quotes', { method: 'POST', body: { site_id: siteId } }),
        update: (id: string, body: QuoteUpdate) => api<Item<Quote>>(`/quotes/${id}`, { method: 'PATCH', body }),
        remove: (id: string) => api(`/quotes/${id}`, { method: 'DELETE' }),
        duplicate: (id: string) => api<Item<Quote>>(`/quotes/${id}/duplicate`, { method: 'POST' }),

        addLine: (id: string, body: QuoteLineInput) => api<QuoteLineResponse>(`/quotes/${id}/lines`, { method: 'POST', body }),
        updateLine: (id: string, lineId: string, body: QuoteLineInput) => api<QuoteLineResponse>(`/quotes/${id}/lines/${lineId}`, { method: 'PATCH', body }),
        removeLine: (id: string, lineId: string) => api(`/quotes/${id}/lines/${lineId}`, { method: 'DELETE' }),
        reorderLines: (id: string, ids: string[]) => api(`/quotes/${id}/lines/reorder`, { method: 'POST', body: { ids } }),

        calculate: (id: string) => api<Item<Quote>>(`/quotes/${id}/calculate`, { method: 'POST' }),
        preview: (id: string, lines: PreviewLineInput[]) => api<PreviewResponse>(`/quotes/${id}/preview-calculate`, { method: 'POST', body: { lines } }),

        transition: (id: string, to: TransitionTarget) => api<TransitionResponse>(`/quotes/${id}/transition`, { method: 'POST', body: { to } }),
        /** Il PDF come Blob: serve il Bearer, quindi niente link diretto. */
        pdf: (id: string) => api(`/quotes/${id}/pdf`, { responseType: 'blob', headers: { Accept: 'application/pdf' } }) as Promise<Blob>,

        completion: (id: string) => api<Item<CompletionItem[]>>(`/quotes/${id}/completion`),
        complete: (id: string, consumptions: Consumption[]) => api<CompleteResponse>(`/quotes/${id}/complete`, { method: 'POST', body: { consumptions } }),
    }
}
