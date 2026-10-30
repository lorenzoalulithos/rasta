import type { JobUnit } from '~/types/api'

/**
 * Unità delle lavorazioni (docs/flows.md §2.3): `short` accanto ai numeri ("181 mq", "2 ore"),
 * `per` dopo un prezzo ("4,00 €/mq", "50,00 €/ora"), `choice` nei segmented.
 */
export const JOB_UNITS: Record<JobUnit, { short: string, per: string, choice: string }> = {
    sqm: { short: 'mq', per: 'mq', choice: 'mq' },
    hour: { short: 'ore', per: 'ora', choice: 'ore' },
    pc: { short: 'pz', per: 'pz', choice: 'unità' },
    pair: { short: 'coppie', per: 'coppia', choice: 'coppia' },
    lm: { short: 'ml', per: 'ml', choice: 'ml' },
}

const quantity = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 3 })

/** "181", "40,222", "2,5": quantità senza zeri inutili. */
export function formatQuantity(value: number): string {
    return quantity.format(value)
}
