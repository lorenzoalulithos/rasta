// Tipi delle risorse API, scritti a mano da docs/api.md e dalle API Resource di Laravel
// (docs/stack.md "Tipi"). Si aggiornano insieme: se cambia una Resource, cambia anche qui.
// Importi e quantità sono numeri, date in ISO 8601.

// ── Enum (mirror di api/app/Enums) ──────────────────────────────────────────

export type Trade = 'painter'
export type TaxRegime = 'forfettario' | 'ordinario'
export type CoverageStrategy = 'min' | 'avg' | 'max'
export type PackageRounding = 'up' | 'fractional'
export type MaterialCategory = 'paint_interior' | 'paint_exterior' | 'enamel' | 'wood' | 'filler' | 'primer' | 'solvent' | 'consumable' | 'tool' | 'other'
export type MaterialKind = 'consumable_quantity' | 'consumable_checklist' | 'tool'
export type BaseUnit = 'lt' | 'kg' | 'm' | 'pc'
export type JobUnit = 'sqm' | 'hour' | 'pc' | 'pair' | 'lm'
export type JobMaterialRole = 'primary' | 'secondary' | 'checklist'
export type ConsumptionMode = 'coverage' | 'per_unit' | 'none'
export type QuoteStatus = 'draft' | 'sent' | 'accepted' | 'rejected' | 'completed'
export type StockMovementType = 'purchase' | 'consumption' | 'adjustment'
export type StockStatus = 'ok' | 'low' | 'negative'

// ── Involucri delle risposte ────────────────────────────────────────────────

export interface Item<T> {
    data: T
}

export interface PaginationMeta {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
    path: string
    links: { url: string | null, label: string, active: boolean }[]
}

export interface Paginated<T> {
    data: T[]
    meta: PaginationMeta
    links: {
        first: string | null
        last: string | null
        prev: string | null
        next: string | null
    }
}

/** Parametri comuni delle liste paginate. */
export interface PageQuery {
    page?: number
    per_page?: number
}

/** Errore di validazione Laravel (422). */
export interface ValidationErrorBody {
    message: string
    errors: Record<string, string[]>
}

// ── Auth e tenant ───────────────────────────────────────────────────────────

export interface Tenant {
    id: string
    name: string
    address: string | null
    zip: string | null
    city: string | null
    province: string | null
    vat_number: string | null
    tax_code: string | null
    email: string | null
    phone: string | null
    logo_path: string | null
    tax_regime: TaxRegime
    default_vat_rate: number
    quote_validity_days: number
    quote_footer_text: string | null
    default_payment_terms: string | null
    coverage_strategy: CoverageStrategy
    opened_package_ratio: number
    package_rounding: PackageRounding
    trade: Trade
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

// ── Clienti e cantieri ──────────────────────────────────────────────────────

export interface Site {
    id: string
    customer_id: string
    name: string
    /** null = stesso indirizzo del cliente */
    address: string | null
    zip: string | null
    city: string | null
    province: string | null
    notes: string | null
    created_at: string | null
}

export interface Customer {
    id: string
    name: string
    tax_code: string | null
    vat_number: string | null
    email: string | null
    phone: string | null
    address: string | null
    zip: string | null
    city: string | null
    province: string | null
    notes: string | null
    sites_count?: number
    quotes_count?: number
    /** solo nel dettaglio */
    sites?: Site[]
    created_at: string | null
}

export type CustomerInput = Partial<Omit<Customer, 'id' | 'sites_count' | 'quotes_count' | 'sites' | 'created_at'>> & { name: string }
export type SiteInput = Partial<Omit<Site, 'id' | 'customer_id' | 'created_at'>> & { name: string }

// ── Catalogo ────────────────────────────────────────────────────────────────

/** I tre numeri del magazzino (domain.md §3.11). */
export interface StockFigures {
    closed_packages: number
    opened_packages: number
    on_hand: number
    committed: number
    available: number
}

export interface Material {
    id: string
    name: string
    category: MaterialCategory
    kind: MaterialKind
    base_unit: BaseUnit
    package_size: number
    package_label: string
    list_price: number | null
    discount_notes: string | null
    cost_price: number | null
    shipping_excluded: boolean
    coverage_min: number | null
    coverage_max: number | null
    supplier: string | null
    min_stock: number
    notes: string | null
    archived_at: string | null
    stock?: StockFigures
}

/** Preventivo accettato che impegna un materiale (flows.md §3.1–3.2). */
export interface Commitment {
    quote: {
        id: string | null
        number: number | null
        year: number | null
        customer: string | null
        site: string | null
    }
    packages: number
}

export interface StockMovement {
    id: string
    material_id: string
    material?: { name: string, package_label: string }
    type: StockMovementType
    closed_delta: number
    opened_delta: number
    quote_id: string | null
    purchase_id: string | null
    notes: string | null
    occurred_at: string
}

export interface MaterialDetail extends Material {
    stock: StockFigures
    commitments: Commitment[]
    movements: StockMovement[]
}

export type MaterialInput = Partial<Omit<Material, 'id' | 'archived_at' | 'stock'>>

export interface MaterialQuery extends PageQuery {
    search?: string
    category?: MaterialCategory
    kind?: MaterialKind
    archived?: 0 | 1
}

export interface JobMaterial {
    id: string
    material_id: string
    material?: Material
    role: JobMaterialRole
    consumption_mode: ConsumptionMode
    consumption_per_unit: number | null
    apply_coats: boolean
    selected_by_default: boolean
    sort_order: number
}

export interface Job {
    id: string
    name: string
    unit: JobUnit
    unit_price: number
    /** null = la lavorazione non va a mani */
    default_coats: number | null
    avg_time_notes: string | null
    estimated_hours_per_unit: number | null
    show_on_quote_pdf: boolean
    sort_order: number
    active: boolean
    materials?: JobMaterial[]
}

export type JobMaterialInput = Pick<JobMaterial, 'material_id' | 'role' | 'consumption_mode'> & Partial<Pick<JobMaterial, 'consumption_per_unit' | 'apply_coats' | 'selected_by_default' | 'sort_order'>>
export type JobInput = Partial<Omit<Job, 'id' | 'materials'>> & { materials?: JobMaterialInput[] }

// ── Preventivi ──────────────────────────────────────────────────────────────

/** Materiale di una riga con il fabbisogno calcolato (api.md "Formato del calcolo"). */
export interface QuoteLineMaterial {
    id: string | null
    material_id: string
    material?: { name: string, package_label: string, base_unit: BaseUnit }
    role: JobMaterialRole
    consumption_mode: ConsumptionMode
    consumption_per_unit: number | null
    apply_coats: boolean
    selected: boolean
    coverage_used: number | null
    required_quantity: number
    required_packages: number
    unit_cost: number
    estimated_cost: number
}

export interface QuoteLine {
    id: string | null
    /** null = riga libera, senza materiali */
    job_id: string | null
    description: string
    unit: JobUnit
    quantity: number
    coats: number | null
    unit_price: number
    amount: number
    sort_order: number
    materials?: QuoteLineMaterial[]
    estimated_cost?: number
}

export interface QuoteTotals {
    subtotal: number
    vat_amount: number
    total: number
    estimated_cost: number
    estimated_margin: number
    estimated_margin_pct: number | null
    estimated_hours: number | null
}

export interface Quote extends QuoteTotals {
    id: string
    number: number
    year: number
    /** "3/2026" */
    number_label: string
    issued_at: string
    expires_at: string
    status: QuoteStatus
    vat_rate: number
    payment_terms: string | null
    footer_text: string | null
    private_notes: string | null
    accepted_at: string | null
    completed_at: string | null
    site?: Site
    customer?: { id: string, name: string } | null
    /** solo nel dettaglio */
    lines?: QuoteLine[]
    created_at: string | null
}

export interface QuoteQuery extends PageQuery {
    status?: QuoteStatus
    search?: string
}

export type QuoteUpdate = Partial<Pick<Quote, 'private_notes' | 'payment_terms' | 'vat_rate' | 'issued_at'>>

export interface QuoteLineMaterialInput {
    material_id: string
    selected?: boolean
    consumption_mode?: Exclude<ConsumptionMode, 'none'>
    consumption_per_unit?: number | null
    apply_coats?: boolean
}

export interface QuoteLineInput {
    job_id?: string | null
    description?: string | null
    unit?: JobUnit
    quantity?: number
    coats?: number | null
    unit_price?: number | null
    materials?: QuoteLineMaterialInput[]
}

/** Riga di preview-calculate: `id` di una riga salvata per calcolarla come il suo PATCH. */
export interface PreviewLineInput extends QuoteLineInput {
    id?: string
}

/** Risposta di POST/PATCH lines: la riga e il preventivo con i totali aggiornati. */
export interface QuoteLineResponse {
    data: QuoteLine
    quote: Quote
}

export interface PreviewResponse {
    data: {
        lines: QuoteLine[]
        totals: QuoteTotals
    }
}

export type TransitionTarget = 'sent' | 'accepted' | 'rejected' | 'draft'

export interface StockWarning {
    material_id: string
    name: string
    package_label: string
    available: number
    min_stock: number
    to_buy: number
}

export interface TransitionResponse {
    data: Quote
    stock_warnings: StockWarning[]
}

/** Proposta di chiusura cantiere per un materiale (domain.md §5 regola 10). */
export interface CompletionItem {
    material: { id: string, name: string, package_label: string }
    estimated_packages: number
    suggested_closed: number
    suggested_opened: number
    stock: { closed_packages: number, opened_packages: number }
}

export interface Consumption {
    material_id: string
    closed: number
    opened: number
}

export interface CompleteResponse {
    data: Quote
    adjustments: { material_id: string, name: string, closed_delta: number }[]
}

// ── Magazzino e acquisti ────────────────────────────────────────────────────

export interface StockRow extends StockFigures {
    material_id: string
    material: Pick<Material, 'id' | 'name' | 'category' | 'kind' | 'base_unit' | 'package_label'>
    min_stock: number
    to_buy: number
    status: StockStatus
    /** solo con `filter=to_buy` */
    commitments?: Commitment[]
}

export interface StockQuery extends PageQuery {
    filter?: 'to_buy'
    category?: MaterialCategory
    search?: string
}

export interface PurchaseLine {
    id: string
    material_id: string
    material: { name: string | null, package_label: string | null }
    packages: number
    unit_cost: number
}

export interface Purchase {
    id: string
    supplier: string | null
    purchased_at: string
    paid: boolean
    total: number | null
    notes: string | null
    lines_count?: number
    /** solo nel dettaglio */
    lines?: PurchaseLine[]
    created_at: string | null
}

export interface PurchaseInput {
    supplier?: string | null
    purchased_at: string
    paid: boolean
    notes?: string | null
    lines: { material_id: string, packages: number, unit_cost: number }[]
    /** materiali di cui aggiornare il prezzo di listino con quello pagato */
    update_prices?: string[]
}

// ── Home ────────────────────────────────────────────────────────────────────

/** GET /dashboard: i tre blocchi della home (docs/flows.md §1). */
export interface Dashboard {
    to_buy_count: number
    /** ultimi 5 draft/sent/accepted, senza righe */
    open_quotes: Quote[]
    /** accettati da almeno 14 giorni, il più vecchio prima */
    to_complete: Quote[]
}
