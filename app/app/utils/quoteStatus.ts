import type { QuoteStatus } from '~/types/api'

type BadgeColor = 'primary' | 'neutral' | 'success' | 'error' | 'info'

/** Etichetta, colore e icona di ogni stato: sempre parola + icona, mai solo colore. */
export const QUOTE_STATUSES: Record<QuoteStatus, { label: string, filter: string, color: BadgeColor, variant: 'solid' | 'subtle', icon: string }> = {
    draft: { label: 'Bozza', filter: 'Bozze', color: 'neutral', variant: 'subtle', icon: 'i-lucide-pencil' },
    sent: { label: 'Inviato', filter: 'Inviati', color: 'primary', variant: 'solid', icon: 'i-lucide-send' },
    accepted: { label: 'Accettato', filter: 'Accettati', color: 'success', variant: 'subtle', icon: 'i-lucide-check' },
    completed: { label: 'Chiuso', filter: 'Chiusi', color: 'neutral', variant: 'solid', icon: 'i-lucide-flag' },
    rejected: { label: 'Rifiutato', filter: 'Rifiutati', color: 'error', variant: 'subtle', icon: 'i-lucide-x' },
}
