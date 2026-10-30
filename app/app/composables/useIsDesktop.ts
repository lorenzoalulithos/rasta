/**
 * true da `lg` (1024px) in su: i bottom sheet diventano pannelli laterali (docs/flows.md §2).
 */
export function useIsDesktop() {
    const isDesktop = ref(false)

    onMounted(() => {
        const query = window.matchMedia('(min-width: 1024px)')
        const update = () => {
            isDesktop.value = query.matches
        }

        update()
        query.addEventListener('change', update)
        onBeforeUnmount(() => query.removeEventListener('change', update))
    })

    return isDesktop
}
