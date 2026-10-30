// Formati italiani per importi e date: "974,00 €", "30/10/2026"
const euro = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' })
const shortDate = new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' })

export function formatEuro(value: number | null | undefined): string {
    return euro.format(value ?? 0)
}

/** Data ISO ("2026-10-30" o con l'ora) in "30/10/2026". */
export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return ''
    }

    // Una data senza ora va letta come giorno locale, non come mezzanotte UTC
    const date = /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00`) : new Date(value)

    return shortDate.format(date)
}

/** "181", "4,50", "4.50" → numero; null se non è un numero. Si scrive con la virgola, come sul telefono. */
export function parseNumber(value: string | number | null | undefined): number | null {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null
    }

    const normalized = (value ?? '').trim().replace(/\s/g, '').replace(',', '.')

    if (normalized === '' || !/^-?\d*\.?\d+$/.test(normalized)) {
        return null
    }

    return Number(normalized)
}
