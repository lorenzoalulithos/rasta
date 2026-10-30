/**
 * Path senza la barra finale: l'hosting statico serve `/login/` per `/login`.
 */
export function normalizePath(path: string): string {
    return path.length > 1 ? path.replace(/\/+$/, '') : path
}
