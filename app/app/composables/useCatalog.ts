import type { Item, Job, JobInput, Material, MaterialDetail, MaterialInput, MaterialQuery, PageQuery, Paginated } from '~/types/api'

/**
 * Lavorazioni e materiali (docs/api.md "Catalogo"), più il "carica esempio" del primo avvio. Solo chiamate.
 */
export function useCatalog() {
    const api = useApi()

    return {
        jobs: (query: PageQuery & { active?: 0 | 1 } = {}) => api<Paginated<Job>>('/jobs', { query }),
        job: (id: string) => api<Item<Job>>(`/jobs/${id}`),
        createJob: (body: JobInput) => api<Item<Job>>('/jobs', { method: 'POST', body }),
        updateJob: (id: string, body: JobInput) => api<Item<Job>>(`/jobs/${id}`, { method: 'PATCH', body }),
        /** 200 con la lavorazione disattivata se usata nei preventivi, 204 se eliminata. */
        removeJob: (id: string) => api<Item<Job> | undefined>(`/jobs/${id}`, { method: 'DELETE' }),
        reorderJobs: (ids: string[]) => api('/jobs/reorder', { method: 'POST', body: { ids } }),

        materials: (query: MaterialQuery = {}) => api<Paginated<Material>>('/materials', { query }),
        material: (id: string) => api<Item<MaterialDetail>>(`/materials/${id}`),
        createMaterial: (body: MaterialInput) => api<Item<Material>>('/materials', { method: 'POST', body }),
        updateMaterial: (id: string, body: MaterialInput) => api<Item<Material>>(`/materials/${id}`, { method: 'PATCH', body }),
        /** 200 con il materiale archiviato se è solo in preventivi chiusi, 204 se eliminato. */
        removeMaterial: (id: string) => api<Item<Material> | undefined>(`/materials/${id}`, { method: 'DELETE' }),

        seed: () => api('/tenant/seed-catalog', { method: 'POST' }),
    }
}
