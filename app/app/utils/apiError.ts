import type { ValidationErrorBody } from '~/types/api'

interface FetchErrorLike {
    response?: { status: number, _data?: Partial<ValidationErrorBody> }
}

/** Errori 422 di Laravel nel formato di UForm: `{ name, message }`. */
export function validationErrors(error: unknown): { name: string, message: string }[] {
    const errors = (error as FetchErrorLike).response?._data?.errors ?? {}

    return Object.entries(errors).map(([name, messages]) => ({ name, message: messages[0] ?? '' }))
}

/** Messaggio da mostrare all'utente, in italiano semplice. */
export function errorMessage(error: unknown, fallback = 'Qualcosa non ha funzionato. Riprova.'): string {
    const response = (error as FetchErrorLike).response

    if (response === undefined) {
        return 'Non riesco a collegarmi. Controlla la connessione e riprova.'
    }

    return response._data?.message ?? fallback
}
